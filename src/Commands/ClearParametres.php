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
    protected string $group = 'Housekeeping';

    /**
     * {@inheritDoc}
     */
    protected string $name = 'parametres:clear';

    /**
     * {@inheritDoc}
     */
    protected string $description = 'Efface tous les paramètres de la base de données.';

    /**
     * {@inheritDoc}
     */
    protected array $options = [
        '--yes|-y' => 'Lance la suppression des paramètres sans demander une confirmation.',
    ];

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function handle()
    {
        $handlers = $this->getHandlers(config('parametres'));

        if ($handlers === []) {
            $this->write("Aucun gestionnaire n'est disponible pour la suppression dans le fichier de configuration.");

            return;
        }


        if (! ($this->option('yes') || $this->confirm('Cette opération supprimera tous les paramètres de "' . $handlers . '". Êtes-vous sûr de vouloir continuer ?', 'n'))) {
            return;
        }

        service('parametres')->flush();

		$single = count($handlers) === 1;

        $this->writer->ok(
			sprintf('Paramètres effacés %s gestionnaire%s %s',
				$single ? 'du' : 'des',
				$single ? '' : 's',
				$single ? '"' . $handlers[0] . '"' : implode(', ', $handlers)
			)
		);
    }

    /**
     * Renvoie une liste des gestionnaires.
     */
    private function getHandlers(array $config): array
    {
        if ($config['handlers'] === []) {
            return [];
        }

        $handlers = [];

        foreach ($config['handlers'] as $handler) {
            // Afficher uniquement les gestionnaires accessibles en écriture (ceux qui peuvent être vidés)
            if (isset($config[$handler]['writeable']) && $config[$handler]['writeable'] === true) {
                $handlers[] = $handler;
            }
        }

		return $handlers;
    }
}
