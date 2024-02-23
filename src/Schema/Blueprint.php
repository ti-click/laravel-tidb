<?php
/**
 * Copyright 2021 Colopl Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Colopl\TiDB\Schema;

use Closure;
use Illuminate\Database\Schema\Blueprint as BaseBluePrint;
use Illuminate\Database\Schema\ForeignIdColumnDefinition as ForeignIdColumnDefinition;

class Blueprint extends BaseBluePrint
{
    /**
     * @var int
     */
    public $shardRowIdBits;

    /**
     * @var int
     */
    public $preSplitRegions;

    /**
     * @var array
     */
    public $defaultIdOptions;


    private function getDefaultIdOptionsFromEnv()
    {
        $defaultIdOptions = [
            'autoIncrement' => true,
            'autoRandom' => false,
            'unsigned' => false
        ];
        if (getenv('DB_DEFAULT_ID_OPTION_AUTO_INCREMENT')) {
            $defaultIdOptions['autoIncrement'] = getenv('DB_DEFAULT_ID_OPTION_AUTO_INCREMENT') === '1';
        }
        if (getenv('DB_DEFAULT_ID_OPTION_AUTO_RANDOM')) {
            $defaultIdOptions['autoRandom'] = getenv('DB_DEFAULT_ID_OPTION_AUTO_RANDOM') === '1';
            $defaultIdOptions['autoIncrement'] = !$defaultIdOptions['autoRandom'];
        }
        if (getenv('DB_DEFAULT_ID_OPTION_UNSIGNED')) {
            $defaultIdOptions['unsigned'] = getenv('DB_DEFAULT_ID_OPTION_UNSIGNED') === '1';
        }
        return $defaultIdOptions;
    }

    public function __construct($table, Closure $callback = null, $prefix = '')
    {
        $this->defaultIdOptions = $this->getDefaultIdOptionsFromEnv();
        parent::__construct($table, $callback, $prefix);
    }

    /**
     * @param string $column
     * @return ColumnDefinition
     */
    public function id($column = 'id', $options = [])
    {
        $options = array_merge($this->defaultIdOptions, $options);

        if ($options['autoRandom']) {
            return $this->bigInteger($column, false, $options['unsigned'])->autoRandom();
        } else {
            return $this->bigInteger($column, $options['autoIncrement'], $options['unsigned']);
        }
    }


    /**
     * Create a new unsigned big integer (8-byte) column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ForeignIdColumnDefinition
     */
    public function foreignId($column, $options = [])
    {
        $options = array_merge($this->defaultIdOptions, $options);

        return $this->addColumnDefinition(new ForeignIdColumnDefinition($this, [
            'type' => 'bigInteger',
            'name' => $column,
            'autoIncrement' => false,
            'unsigned' => $options['unsigned'],
        ]));
    }

    /**
     * @param string $column
     * @param false $autoIncrement
     * @param false $unsigned
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function bigInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return parent::bigInteger($column, $autoIncrement, $unsigned);
    }

    /**
     * @param  string  $type
     * @param  string  $name
     * @param  array  $parameters
     * @return ColumnDefinition
     */
    public function addColumn($type, $name, array $parameters = [])
    {
        $this->columns[] = $column = new ColumnDefinition(
            array_merge(compact('type', 'name'), $parameters)
        );

        return $column;
    }

    /**
     * @param int $bits
     */
    public function shardRowIdBits(int $bits)
    {
        $this->shardRowIdBits = $bits;
    }

    /**
     * @param int $regions
     */
    public function preSplitRegions(int $regions)
    {
        $this->preSplitRegions = $regions;
    }

    /**
     * @return void
     */
    public function useAndNullifyIndexCommands(callable $callback)
    {
        foreach ($this->commands as $index => $command) {
            if (in_array($command->name, ['primary', 'unique', 'index', 'spatialIndex'], true)) {
                $callback($command);
                $command->name = 'nothing';
                unset($this->commands[$index]);
            }
        }
    }

    public function compileNothing()
    {
        return [];
    }
}
