<?php
pm_Loader::registerAutoload();
pm_Context::init('smtp2go');
pm_ApiCli::callSbin('saveFileSMTP2GO', array('-firststep'));