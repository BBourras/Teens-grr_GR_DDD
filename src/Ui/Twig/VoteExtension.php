<?php

declare(strict_types=1);

namespace App\Ui\Twig;

use App\Domain\Enum\VoteType;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Extension Twig pour les votes/réactions.
 *
 * Cette extension expose les méthodes de VoteType de manière propre et lisible dans les templates.
 */
final class VoteExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // Affichage complet : 😂 Trop drôle
            new TwigFilter('vote_display', fn(VoteType $type): string => $type->displayLabel()),

            // Emoji seul
            new TwigFilter('vote_emoji', fn(VoteType $type): string => $type->emoji()),

            // Label texte seul
            new TwigFilter('vote_label', fn(VoteType $type): string => $type->label()),

            // Clé de traduction
            new TwigFilter('vote_label_key', fn(VoteType $type): string => $type->labelKey()),
        ];
    }

    public function getFunctions(): array
    {
        return [
            // Fonction pour obtenir tous les choix de vote (utile dans un formulaire de test ou admin)
            new TwigFunction('vote_choices', fn(): array => VoteType::formChoices()),

            // Fonction pour obtenir tous les types de votes
            new TwigFunction('vote_types', fn(): array => VoteType::cases()),

            // Score impact (utile pour debug ou affichage avancé)
            new TwigFunction('vote_score_impact', fn(VoteType $type): int => $type->scoreImpact()),
        ];
    }
}