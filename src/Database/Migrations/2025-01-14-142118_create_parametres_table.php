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

namespace BlitzPHP\Schild\Database\Migrations;

use BlitzPHP\Database\Migration\Migration;
use BlitzPHP\Database\Migration\Structure;
use stdClass;

class CreateParametresTable extends Migration
{
    private stdClass $config;

    public function __construct()
    {
        $this->config = (object) config('parametres');
        $this->group  = $this->config->database['group'] ?? 'default';
    }

    /**
     * {@inheritDoc}
     */
    public function up(): void
    {
        $this->create($this->config->database['table'], static function (Structure $table) {
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
        $this->dropIfExists($this->config->database['table']);
    }
}
