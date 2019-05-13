<?php

namespace GW\DQO\Generator;

use Doctrine\DBAL\Types\Type;
use GW\DQO\Generator\Render\Block;
use GW\DQO\Generator\Render\Body;
use GW\DQO\Generator\Render\ClassHead;
use GW\DQO\Generator\Render\Line;

final class Renderer
{
    private const HEADER = '';

    /** @var string */
    private $namespace;

    public function __construct(string $namespace = '\\')
    {
        $this->namespace = $namespace;
    }

    public function onNamespace(string $namespace): self
    {
        return new self($namespace);
    }

    public function renderTableFile(Table $table): string
    {
        $head = new ClassHead($this->namespace, ['use GW\DQO\Table;']);
        $body =
            new Block(
                "final class {$table->name()}Table extends Table",
                ...array_map(
                    function (Column $column): Line {
                        return new Body(
                            "public const {$column->nameConst()} = '{$column->dbName()}';"
                        );
                    },
                    $table->columns()
                ),
                ...[new Body()],
                ...array_map(
                    function (Column $column): Line {
                        return new Block(
                            "public function {$column->methodName()}(): string",
                            new Body(
                                "return \$this->fieldPath(self::{$column->nameConst()});"
                            )
                        );
                    },
                    $table->columns()
                )
            );

        return $head->render() . $body->render();
    }

    public function renderRowFile(Table $table): string
    {
        $head = new ClassHead($this->namespace, ['use GW\DQO\TableRow;']);
        $render =
            new Block(
                "final class {$table->name()}Row extends TableRow",
                ...array_map(
                    function (Column $column) use ($table): Line {
                        $typeDef = $this->typeDef($column);

                        return new Block(
                            "public function {$column->methodName()}(): {$typeDef}",
                            new Body($this->valueReturn($table, $column))
                        );
                    },
                    $table->columns()
                )
            );

        return $head->render() . $render->render();
    }

    private function typeDef(Column $column): string
    {
        switch ($column->type()) {
            case 'integer':
            case 'smallint':
                return 'int';

            case 'string':
            case 'text':
                return 'string';

            case 'datetime':
            case 'datetime_immutable':
            case 'DateTimeImmutable':
                return '\DateTimeImmutable';

            case 'boolean':
                return 'bool';
        }

        return sprintf('%s%s', $column->optional() ? '?' : '', $column->type());
    }

    private function valueReturn(Table $table, Column $column): string
    {
        $const = "{$table->name()}Table::{$column->nameConst()}";

        switch ($column->type()) {
            case 'integer':
            case 'smallint':
                return "return \$this->getInt({$const});";

            case 'string':
            case 'text':
                return "return \$this->getString({$const});";

            case 'datetime':
            case 'datetime_immutable':
                return "return \$this->getDateTimeImmutable({$const});";

            case 'boolean':
                return "return \$this->getBool({$const});";
        }

        if ($column->optional()) {
            return "return \$this->getThrough([{$column->type()}::class, 'from'], {$const});";
        }

        return "return {$column->type()}::from(\$this->getString({$const}));";
    }
}
