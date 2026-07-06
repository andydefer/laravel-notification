<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Support;

use Illuminate\Support\Str;

final class StringHelper
{
    /**
     * Convertit une chaîne en kebab-case en supprimant tous les caractères spéciaux.
     *
     * Exemples:
     * - "Hello/World" → "hello-world"
     * - "API/User/Test" → "api-user-test"
     * - "Hello@World!" → "hello-world"
     * - "Hello#World$" → "hello-world"
     * - "Hello%World^" → "hello-world"
     * - "Hello&World*" → "hello-world"
     * - "Hello(World)" → "hello-world"
     * - "Hello=World+" → "hello-world"
     * - "Hello?World" → "hello-world"
     * - "Hello:World" → "hello-world"
     * - "Hello;World" → "hello-world"
     * - "Hello'World" → "hello-world"
     * - "Hello\"World" → "hello-world"
     * - "Hello<World>" → "hello-world"
     * - "Hello,World." → "hello-world"
     * - "Hello|World" → "hello-world"
     * - "Hello\\World" → "hello-world"
     * - "Hello{World}" → "hello-world"
     * - "Hello[World]" → "hello-world"
     * - "Hello~World" → "hello-world"
     * - "Hello`World" → "hello-world"
     *
     * @param  string  $string  La chaîne à convertir
     * @return string La chaîne en kebab-case sans caractères spéciaux
     */
    public static function toKebabCase(string $string): string
    {
        // 1. Normaliser les accents (é → e, à → a, etc.)
        $string = self::removeAccents($string);

        // 2. Remplacer tous les caractères non alphanumériques par des tirets
        //    Garde uniquement: lettres a-z, chiffres 0-9
        $string = preg_replace('/[^a-zA-Z0-9]+/', '-', $string);

        // 3. Insérer un tiret avant les majuscules (HelloWorld → Hello-World)
        $string = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $string);

        // 4. Insérer un tiret entre les majuscules consécutives (XMLParser → XML-Parser)
        $string = preg_replace('/([A-Z])([A-Z][a-z])/', '$1-$2', $string);

        // 5. Tout mettre en minuscules
        $string = strtolower($string);

        // 6. Nettoyer les tirets multiples
        $string = preg_replace('/-+/', '-', $string);

        // 7. Supprimer les tirets au début et à la fin
        $string = trim($string, '-');

        // 8. Si la chaîne est vide, retourner 'empty'
        if (empty($string)) {
            return 'empty';
        }

        return $string;
    }

    /**
     * Supprime les accents d'une chaîne.
     *
     * @param  string  $string  La chaîne à normaliser
     * @return string La chaîne sans accents
     */
    private static function removeAccents(string $string): string
    {
        $accents = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ç' => 'C', 'ç' => 'c',
            'Ñ' => 'N', 'ñ' => 'n',
            'Œ' => 'OE', 'œ' => 'oe',
            'Æ' => 'AE', 'æ' => 'ae',
            'ß' => 'ss',
            'Š' => 'S', 'š' => 's',
            'Ž' => 'Z', 'ž' => 'z',
            'Ÿ' => 'Y', 'ÿ' => 'y',
        ];

        return strtr($string, $accents);
    }

    /**
     * Convertit une chaîne en kebab-case avec UUID.
     *
     * @param  string  $string  La chaîne à convertir
     * @return string La chaîne en kebab-case avec UUID
     */
    public static function toKebabCaseWithUuid(string $string): string
    {
        $kebab = self::toKebabCase($string);
        $uuid = (string) Str::uuid();

        return $kebab.'-'.$uuid;
    }

    /**
     * Génère une signature unique en kebab-case.
     *
     * @param  string  $prefix  Préfixe (ex: "recurring", "delayed")
     * @param  string  $suffix  Suffixe optionnel
     * @return string Signature en kebab-case
     */
    public static function generateSignature(string $prefix, string $suffix = ''): string
    {
        $signature = self::toKebabCase($prefix);

        if (! empty($suffix)) {
            $signature .= '-'.self::toKebabCase($suffix);
        }

        $signature .= '-'.(string) Str::uuid();

        return $signature;
    }

    /**
     * Valide si une chaîne est en kebab-case.
     *
     * @param  string  $string  La chaîne à valider
     * @return bool True si la chaîne est en kebab-case
     */
    public static function isKebabCase(string $string): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9-]*$/', $string);
    }

    /**
     * Nettoie une chaîne pour en faire une signature valide.
     * Supprime tous les caractères qui ne sont pas autorisés.
     *
     * @param  string  $string  La chaîne à nettoyer
     * @return string La chaîne nettoyée
     */
    public static function sanitizeForSignature(string $string): string
    {
        // 1. Normaliser les accents
        $string = self::removeAccents($string);

        // 2. Garder uniquement les lettres, chiffres, tirets, underscores
        $string = preg_replace('/[^a-zA-Z0-9\-_]+/', '-', $string);

        // 3. Remplacer les underscores par des tirets
        $string = str_replace('_', '-', $string);

        // 4. Tout mettre en minuscules
        $string = strtolower($string);

        // 5. Nettoyer les tirets multiples
        $string = preg_replace('/-+/', '-', $string);

        // 6. Supprimer les tirets au début et à la fin
        $string = trim($string, '-');

        // 7. Si la chaîne est vide, retourner 'empty'
        if (empty($string)) {
            return 'empty';
        }

        return $string;
    }

    /**
     * Remplace tous les caractères spéciaux par leur équivalent lisible.
     *
     * @param  string  $string  La chaîne à traiter
     * @return string La chaîne avec les caractères spéciaux remplacés
     */
    public static function replaceSpecialChars(string $string): string
    {
        $replacements = [
            '/' => '-',
            '\\' => '-',
            '|' => '-',
            ':' => '-',
            ';' => '-',
            ',' => '-',
            '.' => '-',
            '?' => '-',
            '!' => '-',
            '@' => '-',
            '#' => '-',
            '$' => '-',
            '%' => '-',
            '^' => '-',
            '&' => '-',
            '*' => '-',
            '(' => '-',
            ')' => '-',
            '=' => '-',
            '+' => '-',
            '[' => '-',
            ']' => '-',
            '{' => '-',
            '}' => '-',
            '<' => '-',
            '>' => '-',
            '~' => '-',
            '`' => '-',
            '"' => '-',
            "'" => '-',
            ' ' => '-',
            '_' => '-',
        ];

        return strtr($string, $replacements);
    }
}
