<?='<?' . 'php'?> declare(strict_types=1);

<?php if (isset($namespace)): ?>
namespace <?=$namespace?>;

<?php endif; ?>
use PHPUnit\Framework\TestCase;

/**
 * Test <?=$name?>

<?php if (isset($namespace)): ?>
 * 
 * @package <?=$namespace?>

<?php endif; ?>
 */
final class <?=$name?> extends TestCase
{
    
}
