<?php

declare(strict_types=1);

/**
 * This file is part of BlitzPHP Parametres.
 *
 * (c) 2025 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Parametres\Database\Migrations;

use BlitzPHP\Database\Migration\Builder;
use BlitzPHP\Database\Migration\Migration;
use stdClass;

class CreateParametresTable extends Migration
{
    private stdClass $config;

	private string $group;

    public function __construct()
    {
        $this->config = (object) config('parametres');
        $this->group  = $this->config->database['group'] ?? config('database.connection', 'default');
    }

	/**
	 * {@inheritDoc}
	 */
	public function shouldRun(): bool
    {
		$handlers = [];

        foreach ($this->config->handlers as $handler) {
            if (isset($this->config->{$handler}['writeable']) && $this->config->{$handler}['writeable'] === true) {
                $handlers[] = $handler;
            }
        }

        return in_array('database', $handlers);
    }

    /**
     * {@inheritDoc}
     */
    public function up(): void
    {
        $this->connection($this->group)->create($this->config->database['table'], static function (Builder $table) {
            $table->id();
            $table->string('file');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type', 31)->default('string');
            $table->string('context')->nullable();
            $table->timestamps();

            return $table;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function down(): void
    {
        $this->connection($this->group)->dropIfExists($this->config->database['table']);
    }
}
