<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Function;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\TokenType;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\AST\Node;

class PowerFunction extends FunctionNode
{
    private Node $base;
    private Node $exponent;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->base = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->exponent = $parser->ArithmeticPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'POWER(' .
            $this->base->dispatch($sqlWalker) . ', ' .
            $this->exponent->dispatch($sqlWalker) .
            ')';
    }
}
