<?php
pm_Loader::registerAutoload();
pm_Context::init('smtp2go');
if(!file_exists('/etc/postfix/main.cf'))
{
    echo "Unable to find file /etc/postfix/main.cf";
    $this->_status->addMessage('error');
}

