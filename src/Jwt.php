<?php

namespace Ziphp\Recipes;

use DateTimeImmutable;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\JwtFacade;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\UnencryptedToken;
use Throwable;
use Yii;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Builder as JwtBuilder;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Validation\Constraint;

final class Jwt
{
    private static function createJwtFacade(): JwtFacade
    {
        return (new JwtFacade(clock: SystemClock::fromSystemTimezone()));
    }

    public static function createJwtToken(array $contains, string $timeModifier): UnencryptedToken
    {
        $algorithm    = new Sha256();
        $key   = InMemory::plainText(str_repeat(Yii::$app->request->cookieValidationKey, 2));

        return self::createJwtFacade()->issue(
            $algorithm,
            $key,
            static function (
                JwtBuilder $builder,
                DateTimeImmutable $issuedAt
            ) use ($contains, $timeModifier): JwtBuilder {
                $issuer = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['SERVER_NAME'];

                $builder = $builder->issuedBy($issuer)
                    ->permittedFor($_SERVER['HTTP_ORIGIN'] ?? $issuer)
                    ->expiresAt($issuedAt->modify($timeModifier));

                foreach ($contains as $k => $v) {
                    $builder = $builder->withClaim($k, $v);
                }

                return $builder;
            }
        );
    }

    public static function parseJwtToken(string $unsafe_string): UnencryptedToken|null
    {
        $algorithm    = new Sha256();
        $key   = InMemory::plainText(str_repeat(Yii::$app->request->cookieValidationKey, 2));

        try {
            $unsafe = self::createJwtFacade()->parse(
                $unsafe_string,
                new Constraint\SignedWith($algorithm, $key),
                new Constraint\StrictValidAt(SystemClock::fromSystemTimezone())
            );
        } catch (CannotDecodeContent|InvalidTokenStructure|UnsupportedHeaderFound|Throwable $e) {
            Yii::warning($e->getMessage());
            return null;
        }

        return $unsafe;
    }
}
