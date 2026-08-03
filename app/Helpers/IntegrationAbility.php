<?php

namespace App\Helpers;

/**
 * Catalogo de permisos de los tokens de integracion.
 *
 * A un token de integracion NUNCA se le emite '*': PersonalAccessToken::can()
 * cortocircuita con el comodin, asi que un token asi pasaria cualquier chequeo.
 * La validacion de emision lo impide con Rule::in(self::all()).
 */
final class IntegrationAbility
{
    public const ME_READ = 'integration:me.read';

    public const CATEGORIES_READ = 'integration:categories.read';

    public const CREDENTIALS_CREATE = 'integration:credentials.create';

    /** Metadatos de una credencial, nunca el secreto. */
    public const CREDENTIALS_READ = 'integration:credentials.read';

    /**
     * Listar y buscar credenciales propias. Separada de READ a proposito: READ
     * exige conocer el id de antemano, LIST permite enumerar. Son superficies
     * distintas y un token debe poder tener una sin la otra.
     */
    public const CREDENTIALS_LIST = 'integration:credentials.list';

    public const CREDENTIALS_UPDATE = 'integration:credentials.update';

    /** Devuelve el secreto en claro. Se emite solo si se pide explicitamente. */
    public const CREDENTIALS_REVEAL = 'integration:credentials.reveal';

    public const CREDENTIALS_DELETE = 'integration:credentials.delete';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ME_READ,
            self::CATEGORIES_READ,
            self::CREDENTIALS_CREATE,
            self::CREDENTIALS_READ,
            self::CREDENTIALS_LIST,
            self::CREDENTIALS_UPDATE,
            self::CREDENTIALS_REVEAL,
            self::CREDENTIALS_DELETE,
        ];
    }

    /**
     * Set por defecto cuando el humano no elige. Sin delete: borrar una
     * credencial desde fuera es desproporcionado y no tiene vuelta atras.
     *
     * @return list<string>
     */
    public static function defaults(): array
    {
        return [
            self::ME_READ,
            self::CATEGORIES_READ,
            self::CREDENTIALS_CREATE,
            self::CREDENTIALS_READ,
            self::CREDENTIALS_LIST,
            self::CREDENTIALS_UPDATE,
            self::CREDENTIALS_REVEAL,
        ];
    }
}
