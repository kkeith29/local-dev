<?php declare(strict_types=1);

namespace App\Services\CodeGenerator\Enums;

use const DIRECTORY_SEPARATOR as DS;

enum FileType: string
{
    case FIL = 'file';
    case CLS = 'class';
    case ENM = 'enum';
    case ENM_INT = 'enum-int';
    case ENM_STR = 'enum-string';
    case TRT = 'trait';
    case ITF = 'interface';

    case CLS_TEST = 'class-test';
    case ENM_TEST = 'enum-test';
    case TRT_TEST = 'trait-test';

    /**
     * Determine file type from file path
     */
    public static function fromFilePath(string $path): self
    {
        $types = [
            'class' => self::CLS,
            'enum' => self::ENM,
            'trait' => self::TRT,
            'interface' => self::ITF
        ];
        $path = strtolower($path);
        $found = self::CLS;
        foreach ($types as $match => $type) {
            if (!str_contains($path, $match)) {
                continue;
            }
            $found = $type;
        }
        if (str_contains($path, 'test')) {
            $found = match($found) {
                self::CLS => self::CLS_TEST,
                self::TRT => self::TRT_TEST,
                self::ENM => self::ENM_TEST,
                default => $found
            };
        }
        return $found;
    }

    /**
     * Get template path for file type
     */
    public function getTemplatePath(): string
    {
        $file = match($this) {
            self::CLS => 'class.php',
            self::ENM, self::ENM_INT, self::ENM_STR => 'enum.php',
            self::TRT => 'trait.php',
            self::ITF => 'interface.php',
            self::FIL => 'file.php',
            self::CLS_TEST, self::ENM_TEST, self::TRT_TEST => 'test.php'
        };
        return PATH_RESOURCE . 'templates' . DS . $file;
    }

    /**
     * Get template vars for type
     */
    public function getTemplateVars(): array
    {
        return match($this) {
            self::CLS, self::ENM, self::TRT, self::ITF, self::FIL, self::CLS_TEST, self::ENM_TEST, self::TRT_TEST => [],
            self::ENM_INT => ['backed_type' => 'int'],
            self::ENM_STR => ['backed_type' => 'string']
        };
    }
}

