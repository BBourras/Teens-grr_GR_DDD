<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Function;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\TokenType;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * Fonction personnalisée POW(base, exponent) pour Doctrine DQL.
 *
 * Permet d'utiliser POW() dans les QueryBuilder comme une fonction native.
 * Nécessaire car Doctrine ne reconnaît pas POW/POWER par défaut.
 */
final class PowFunction extends FunctionNode
{
    /** @var mixed */
    private $base;

    /** @var mixed */
    private $exponent;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);           // POW
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->base = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);

        $this->exponent = $parser->ArithmeticPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'POW(' .
            $this->base->dispatch($sqlWalker) . ', ' .
            $this->exponent->dispatch($sqlWalker) .
        ')';
    }
}