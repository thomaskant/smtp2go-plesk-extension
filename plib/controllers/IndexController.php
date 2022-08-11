<?php

class IndexController extends pm_Controller_Action
{
    private static $buttonDecorators = array(
            'ViewHelper', 
            array
            (
                array('data'  => 'HtmlTag'),
                array('tag'   => 'div', 'class' => 'field-value')
            ), 
            array
            (
                array('data'  => 'HtmlTag'),
                array('tag'   => 'span', 'class' => 'btn action',  'id' => 'btn-send',),
            ),
            array
            (
                array('label' => 'HtmlTag'), 
                array('tag'   => 'div', 'placement'  => 'prepend', 'class' => 'field-name')
            ), 
            array
            (
                array('row'   => 'HtmlTag'), 
                array('tag'   => 'div',  'class' => 'form-row', 'style' => 'margin-top:10px;')
            ) 
       ); 
    
    
    public function indexAction()
    {
        $this->_forward('postfix');
    }
    
    public function init()
    {
        parent::init();
        $this->view->pageTitle = '<img class="logotitle" src="/modules/smtp2go/logo.svg" alt="SMTP2GO Logo" width="120">'. pm_Locale::lmsg('SMTP2GO');
        $this->view->tabs = array(
            array(
                'title'  => pm_Locale::lmsg('configuration'),
                'action' => 'postfix'
            ),
            array(
                'title' => pm_Locale::lmsg('test_connection'),
                'action' => 'testcon',
            ),
        );
    }

    
    public function testconAction()
    {
        if($_GET['result'] == 1)
        {
            $result = pm_ApiCli::callSbin('saveFileSMTP2GO', array('-getLog'));
            $form   = new pm_Form_Simple();
            $form   
                    ->setMethod('post')
                    ->setAttrib('id', 'logs')
                    ->addElement('textarea', 'details', array(
                        'label'         => pm_Locale::lmsg('details'),
                        'value'         => $result['stdout']
                    ));
        }
        elseif($this->_request->isPost()) 
        {
            $client = pm_Session::getClient();
            $postData = $this->_request->getPost();
            $result = pm_ApiCli::callSbin('saveFileSMTP2GO', array('-testCon', (string) $client->getProperty('email'), (string) $postData['subject']));
            $this->_status->addMessage('info', pm_Locale::lmsg('message_sent'));
            $this->_helper->json(array('redirect' => '/modules/smtp2go/index.php/index/testcon?result=1'));
        } else 
        {
            $form   = new pm_Form_Simple();
            $form   
                    ->setMethod('post')
                    ->setAttrib('id', 'logs')
                    ->setAttrib('name', 'form')                    
                    ->addElement('button', 'send', array(
                        'label'         => pm_Locale::lmsg('click_to_proceed'),
                        'onclick'       => "document.form.submit();"
                        //'decorators'    => self::$buttonDecorators
                    ));
        }
        
        $this->view->form = $form;
    }

    public function postfixAction()
    {
        $details = unserialize(pm_Settings::get('SMTP2GOSettings'));
        $result  = pm_ApiCli::callSbin('saveFileSMTP2GO', array('-readFile'));
        $form    = new pm_Form_Simple();
        
        $form   
                ->setMethod('post')
                ->setAttrib('id', 'postfix')
                ->setAttrib('name', 'form')
                ->addElement('select', 'smtp_sasl_status', array(
                    'label'         => pm_Locale::lmsg('smtp_sasl_status'),
                    'required'      => true,
                    'multiOptions'  => array(
                        1           => 'Enabled',
                        0           => 'Disabled',
                    ),
                    'value'         => $details['smtp_sasl_status']
                ))                
                ->addElement('text', 'username', array(
                    'label'         => pm_Locale::lmsg('username'),
                    'required'      => true,
                    'value'         => $details['username'],
                    'validators'    => array(
                        array('NotEmpty', true),
                    ),
                ))
                ->addElement('text', 'password', array(
                    'label'         => pm_Locale::lmsg('password'),
                    'value'         => $details['password'],
                    'required'      => true,
                    'validators'    => array(
                        array('NotEmpty', true),
                    ),                    
                ))
                ->addElement('button', 'send', array(
                    'label'         => pm_Locale::lmsg('save_changes'),
                    'decorators'    => self::$buttonDecorators,
                    'onclick'       => "document.form.submit();"
                    
                )); 

         if($this->_request->isPost() && $form->isValid($this->getRequest()->getPost())) {
             $postData = $this->_request->getPost();
             pm_Settings::set('SMTP2GOSettings', serialize($postData));
             $res = pm_ApiCli::callSbin('saveFileSMTP2GO', array('-setConf', (string) $postData['username'], (string) $postData['password']));
             if($res['stdout'] != 1)
             {
                 $this->_status->addMessage('error', pm_Locale::lmsg('unableToSave'));
                 $this->_helper->json(array('redirect' => '/modules/smtp2go/index.php/index/postfix'));
             }
             $status = $postData['smtp_sasl_status'];
             unset($postData['forgery_protection_token'],$postData['username'],$postData['password'], $postData['submit'], $postData['reset'], $postData['testconnection'], $postData['smtp_sasl_status']);
             
             $details = "\n#SMTP2GOCONFIG\n";
             $details .= "relayhost = [mail.smtp2go.com]:2525\n";
             $details .= "smtp_sasl_auth_enable = yes\n";
             $details .= "smtp_sasl_password_maps = hash:/etc/postfix/password\n";
             $details .= "smtp_sasl_security_options = noanonymous\n";
             $details .= "smtpd_sasl_authenticated_header = yes\n";
             $details .= "smtp_tls_security_level = may\n";
             $details .= "header_size_limit = 4096000\n";
             $details .= "#ENDSMTP2GOCONFIG";
             
             $result = pm_ApiCli::callSbin('saveFileSMTP2GO', array('-uninstall'));
             if($status == 1)
             {
                $result = pm_ApiCli::callSbin('saveFileSMTP2GO', array('-saveFile', base64_encode($details)));
             } 
             
             if($result['stdout'] == 1)
             {
                 $this->_status->addMessage('info', pm_Locale::lmsg('settingsSaved')); 
             } 
             else 
             {
                 $this->_status->addMessage('error', pm_Locale::lmsg('unableToSave'));
             }
             
             $this->_helper->json(array('redirect' => '/modules/smtp2go/index.php/index/postfix'));
        }  
                
         $this->view->form = $form;
    }
}