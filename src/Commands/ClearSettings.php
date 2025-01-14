<?php

namespace BlitzPHP\Parametres\Commands;

use BlitzPHP\Cli\Console\Command;

class ClearParametres extends Command
{
	/** {@inheritDoc} */
    protected $group       = 'Housekeeping';

	/** {@inheritDoc} */
    protected $name        = 'parametres:clear';

	/** {@inheritDoc} */
	protected $description = 'Efface tous les paramètres de la base de données.';

	/**
	 * {@inheritDoc}
	 */
    public function execute(array $params)
    {
		if (! $this->confirm('Cette opération supprimera tous les paramètres de la base de données. Êtes-vous sûr de vouloir continuer ?')) {
            return;
        }

        service('parametres')->flush();

		$this->writer->ok('Paramètres effacés de la base de données.');
    }
}
