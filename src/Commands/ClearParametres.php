<?php

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Parametres\Commands;

use BlitzPHP\Cli\Console\Command;

class ClearParametres extends Command
{
    /**
     * {@inheritDoc}
     */
    protected $group = 'Housekeeping';

    /**
     * {@inheritDoc}
     */
    protected $name = 'parametres:clear';

    /**
     * {@inheritDoc}
     */
    protected $description = 'Efface tous les paramètres de la base de données.';

    /**
     * {@inheritDoc}
     */
    protected $options = [
        '--yes|-y' => 'Lance la suppression des paramètres sans demander une confirmation.',
    ];

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function execute(array $params)
    {
        if (! ($this->option('yes') || $this->confirm('Cette opération supprimera tous les paramètres de la base de données. Êtes-vous sûr de vouloir continuer ?', 'n'))) {
            return;
        }

        service('parametres')->flush();

        $this->writer->ok('Paramètres effacés de la base de données.');
    }
}
