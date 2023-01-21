<?='<?' . 'php'?> declare(strict_types=1);

<?php if (isset($namespace)): ?>
namespace <?=$namespace?>;

<?php endif; ?>
/**
 * Enum <?=$name?>

<?php if (isset($namespace)): ?>
 * 
 * @package <?=$namespace?>

<?php endif; ?>
 */
enum <?=$name?><?=(isset($backed_type) ? ": {$backed_type}" : '')?>

{
    
}
