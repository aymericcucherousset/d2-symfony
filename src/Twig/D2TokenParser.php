<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Twig;

use Aymericcucherousset\D2Symfony\Twig\Node\D2Node;
use Twig\Node\AutoEscapeNode;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Parses the `{% d2 %}` tag:
 *
 *   {% d2 with { theme: 200 } %}
 *   user -> app: {{ protocol }}
 *   {% endd2 %}
 *
 * Twig expressions inside the body are not HTML-escaped, as they are part of the D2 source.
 */
final class D2TokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $options = null;
        if ($stream->nextIf(Token::NAME_TYPE, 'with')) {
            $options = $this->parser->parseExpression();
        }

        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'decideD2End'], true);
        $stream->expect(Token::BLOCK_END_TYPE);

        // Twig drops the newline following "%}", so the body starts on the line of its first node.
        return new D2Node(new AutoEscapeNode(false, $body, $lineno), $options, $lineno, $body->getTemplateLine());
    }

    public function decideD2End(Token $token): bool
    {
        return $token->test('endd2');
    }

    public function getTag(): string
    {
        return 'd2';
    }
}
