<?php declare(strict_types=1);

namespace App\Services;

use App\Services\CodeGenerator\Enums\FileType;
use Exception;

use const DIRECTORY_SEPARATOR as DS;

/**
 * Class CodeGeneratorService
 * 
 * @package App\Services
 */
class CodeGeneratorService
{
    /**
     * Find closest composer.json file to directory
     *
     * @param string $directory
     * @return null|string
     */
    protected function getClosestComposerConfigFile(string $directory): ?string
    {
        $paths = explode(DS, trim($directory, DS));
        do {
            $composer_config = DS . implode(DS, $paths) . DS . 'composer.json';
            if (file_exists($composer_config)) {
                return $composer_config;
            }
            array_pop($paths);
        } while (count($paths) > 0);

        return null;
    }

    /**
     * Read and parse composer.json file
     *
     * @param string $file
     * @return array
     * @throws \Exception
     */
    protected function getComposerConfig(string $file): array
    {
        if (($data = file_get_contents($file)) === false) {
            throw new Exception('Unable to read file: ' . $file);
        }
        $data = json_decode($data, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new Exception('Unable to read JSON file: ' . $file);
        }
        return $data;
    }

    /**
     * Get type name and namespace if found from file path 
     *
     * @param string $file_path
     * @return array{0: string, 1: string|null}
     * @throws \Exception
     */
    protected function getTypeInfoFromFile(string $file_path): array
    {
        $directory = dirname($file_path);
        $type_name = basename($file_path, '.' . pathinfo($file_path, PATHINFO_EXTENSION));
        while(($composer_file = $this->getClosestComposerConfigFile($directory)) !== null) {
            $directory = dirname($composer_file);
            $config = $this->getComposerConfig($composer_file);
            $psr4 = $config['autoload']['psr-4'] ?? [];
            if (count($psr4) > 0) {
                foreach ($psr4 as $namespace => $path) {
                    $psr4_path = $this->normalizePath($directory . DS . trim($path, DS));
                    if (!str_starts_with($file_path, $psr4_path)) {
                        continue;
                    }
                    $file_namespace = trim(str_replace($psr4_path, '', dirname($file_path)), DS);
                    $file_namespace = str_replace(DS, '\\', $file_namespace);
                    if ($file_namespace !== '') {
                        $file_namespace = '\\' . $file_namespace;
                    }
                    return [$type_name, rtrim($namespace, '\\') . $file_namespace];
                }
            }
            $directory = dirname($directory);
        }
        return [$type_name, null];
    }

    /**
     * Normalize path by removing unnecessary parts and resolving symlinks
     *
     * @param string $path
     * @return string
     */
    protected function normalizePath(string $path): string
    {
        $is_abs = str_starts_with($path, DS);
        $path = explode(DS, $path);
        $i = 0;
        while(isset($path[$i])) {
            $part = $path[$i];
            if ($part === '' || $part === '.') {
                unset($path[$i]);
            }
            elseif ($part === '..') {
                unset($path[$i]);
                if (isset($path[$i - 1])) {
                    unset($path[$i - 1]);
                }
            }
            $i++;
        }
        $path = ($is_abs ? DS : '') . implode(DS, $path);
        if (file_exists($path)) {
            $path = realpath($path);
        }
        return $path;
    }

    /**
     * Generate file content based on input file and optional file type
     *
     * If no file type is passed, it is determined based on the file path
     *
     * @param string $file_path
     * @param null|\App\Services\CodeGenerator\Enums\FileType $type
     * @return string
     * @throws \Exception
     */
    public function generate(string $file_path, ?FileType $type = null): string
    {
        $file_path = $this->normalizePath($file_path);
        [$name, $namespace] = $this->getTypeInfoFromFile($file_path);
        $type ??= FileType::fromFilePath($file_path);
        // if not pascal case, then assume it's a normal file
        if ($name[0] === strtolower($name[0])) {
            $type = FileType::FIL;
        }
        $template = $type->getTemplatePath();
        $vars = [
            'namespace' => $namespace,
            'name' => $name,
            ...$type->getTemplateVars()
        ];
        return (function(string $__path, array $__vars): string {
            extract($__vars);
            ob_start();
            include($__path);
            $contents = ob_get_contents();
            ob_end_clean();
            return $contents;
        })($template, $vars);
    }
}
