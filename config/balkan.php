<?php

return [
    /*
     * Yönetici rollerinde iki aşamalı doğrulama zorunlu mu (User::MFA_ZORUNLU_ROLLER).
     * Canlıda açık kalmalı. Yerel demo ve testlerde .env / phpunit.xml ile kapatılır.
     */
    'mfa_zorunlu' => (bool) env('MFA_ZORUNLU', true),
];
