<?php

namespace App\Detour\Search;

/** Les stations classées par gain net, la référence et le meilleur choix */
final readonly class SearchResult
{
    /** @param  list<Candidate>  $candidates  du meilleur gain net au moins bon */
    public function __construct(
        public SearchQuery $query,
        public array $candidates,
        public ?Candidate $reference,
    ) {}

    public function best(): ?Candidate
    {
        return $this->candidates[0] ?? null;
    }

    /** Le meilleur choix vaut-il un détour ? Sinon, on fait le plein à la référence */
    public function detourWorthIt(): bool
    {
        $best = $this->best();

        return $best !== null && ! $best->isReference && $best->gain->isWorthIt();
    }
}
