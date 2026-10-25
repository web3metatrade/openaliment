<?php
declare(strict_types=1);
// Save the completed file as config/mail.local.php, outside public/.
// Use the exact SMTP host, encryption and port shown by cPanel for your mailbox.
return [
    'enabled'=>false,
    'transport'=>'smtp',
    'host'=>'',
    'port'=>465,
    'encryption'=>'ssl',
    'username'=>'',
    'password'=>'',
    'from_address'=>'',
    'from_name'=>'OpenAliment',
];
