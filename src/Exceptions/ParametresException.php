<?php

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Parametres\Exceptions;

use RuntimeException;

class ParametresException extends RuntimeException
{
    public static function fileForStorageNotDefined(): self
    {
        return new self("Le fichier de stockage des données n'a pas été définit");
    }

    public static function directoryOfFileNotFound(string $path): self
    {
        return new self("Le répertoire du fichier '{$path}' n'a pas été trouvé");
    }
}
