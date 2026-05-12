<?php

namespace App\Helpers;

class PublicEmailDetector
{
    private const PUBLIC_DOMAINS = [
        'gmail.com', 'googlemail.com',
        'outlook.com', 'hotmail.com', 'hotmail.es', 'hotmail.co.uk',
        'live.com', 'live.es', 'msn.com',
        'yahoo.com', 'yahoo.es', 'yahoo.co.uk', 'yahoo.com.ar',
        'icloud.com', 'me.com', 'mac.com',
        'proton.me', 'protonmail.com',
        'tutanota.com', 'tutamail.com',
        'aol.com', 'aim.com',
        'zoho.com',
        'mail.com', 'email.com', 'inbox.com',
        'yandex.com', 'yandex.ru',
        'gmx.com', 'gmx.net', 'gmx.de',
        'web.de', 'freenet.de',
        'seznam.cz', 'centrum.cz',
        'terra.com.br', 'bol.com.br', 'uol.com.br',
    ];

    public static function isPublic(string $email): bool
    {
        $domain = strtolower(email_domain($email));

        return in_array($domain, self::PUBLIC_DOMAINS, true);
    }

    public static function isCorporate(string $email): bool
    {
        return ! self::isPublic($email);
    }
}
