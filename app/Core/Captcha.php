<?php

declare(strict_types=1);

namespace App\Core;

final class Captcha
{
    public static function issue(string $scope): array
    {
        $a = random_int(2, 9);
        $b = random_int(1, 9);
        $id = bin2hex(random_bytes(12));
        $_SESSION['_captcha'][$scope] = ['id' => $id, 'answer' => $a + $b, 'expires' => time() + 600];
        return ['id' => $id, 'question' => "Quanto é {$a} + {$b}?"];
    }

    public static function verify(string $scope, ?string $id, mixed $answer): bool
    {
        $challenge = $_SESSION['_captcha'][$scope] ?? null;
        unset($_SESSION['_captcha'][$scope]);
        if (!is_array($challenge) || (int) ($challenge['expires'] ?? 0) < time()) {
            return false;
        }
        return is_string($id)
            && hash_equals((string) $challenge['id'], $id)
            && filter_var($answer, FILTER_VALIDATE_INT) !== false
            && (int) $answer === (int) $challenge['answer'];
    }
}
