<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\ValueObjects;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

/**
 * Value Object pour un corps de message basé sur une vue Laravel.
 *
 * Étend MessageBodyVO et génère automatiquement le HTML ou le texte brut
 * à partir de la vue. Peut être utilisé partout où un MessageBodyVO est attendu.
 *
 * @example
 * // HTML (email)
 * $body = new MessageViewBodyVO(
 *     view: 'emails.welcome',
 *     data: StrictAssociative::from(['user' => $user])
 * );
 *
 * // Plain text (SMS)
 * $body = new MessageViewBodyVO(
 *     view: 'sms.welcome',
 *     data: StrictAssociative::from(['user' => $user]),
 *     plainText: true
 * );
 */
final class MessageViewBodyVO extends MessageBodyVO
{
    /**
     * @param  string  $view  Nom de la vue
     * @param  StrictAssociative  $data  Données passées à la vue
     * @param  StrictAssociative  $mergeData  Données fusionnées avec la vue
     * @param  bool  $plainText  Si true, retourne le texte brut (pour SMS)
     */
    public function __construct(
        private readonly string $view,
        private readonly StrictAssociative $data = new StrictAssociative,
        private readonly StrictAssociative $mergeData = new StrictAssociative,
        private readonly bool $plainText = false,
    ) {
        // Appel parent avec le contenu généré
        $renderedContent = $this->renderView();
        parent::__construct($renderedContent);
    }

    /**
     * Rendu de la vue en HTML ou texte brut.
     */
    private function renderView(): string
    {
        /** @var ViewFactory $factory */
        $factory = app(ViewFactory::class);

        /** @var View $view */
        $view = $factory->make(
            view: $this->view,
            data: $this->data->toArray(),
            mergeData: $this->mergeData->toArray(),
        );

        $rendered = $view->render();

        if ($this->plainText) {
            return html_entity_decode(strip_tags($rendered));
        }

        return $rendered;
    }

    /**
     * Récupère le nom de la vue.
     */
    public function getView(): string
    {
        return $this->view;
    }

    /**
     * Récupère les données de la vue.
     */
    public function getData(): StrictAssociative
    {
        return $this->data;
    }

    /**
     * Récupère les données fusionnées.
     */
    public function getMergeData(): StrictAssociative
    {
        return $this->mergeData;
    }

    /**
     * Vérifie si le message est en texte brut.
     */
    public function isPlainText(): bool
    {
        return $this->plainText;
    }

    /**
     * Crée une nouvelle instance avec des données supplémentaires.
     */
    public function withData(array|StrictAssociative $data): self
    {
        $mergeData = StrictAssociative::from($data);

        return new self(
            $this->view,
            $this->data->merge($mergeData->toArray()),
            $this->mergeData,
            $this->plainText,
        );
    }

    /**
     * Crée une nouvelle instance avec des données fusionnées supplémentaires.
     */
    public function withMergeData(array|StrictAssociative $mergeData): self
    {
        $mergeData = StrictAssociative::from($mergeData);

        return new self(
            $this->view,
            $this->data,
            $this->mergeData->merge($mergeData->toArray()),
            $this->plainText,
        );
    }

    /**
     * Crée une nouvelle instance en mode texte brut.
     */
    public function asPlainText(): self
    {
        return new self(
            $this->view,
            $this->data,
            $this->mergeData,
            plainText: true,
        );
    }

    /**
     * Crée une nouvelle instance en mode HTML.
     */
    public function asHtml(): self
    {
        return new self(
            $this->view,
            $this->data,
            $this->mergeData,
            plainText: false,
        );
    }

    /**
     * Crée une instance en texte brut directement.
     *
     * @param  string  $view  Nom de la vue
     * @param  StrictAssociative|array<string, mixed>  $data  Données passées à la vue
     * @param  StrictAssociative|array<string, mixed>  $mergeData  Données fusionnées avec la vue
     */
    public static function plain(
        string $view,
        StrictAssociative|array $data = [],
        StrictAssociative|array $mergeData = [],
    ): self {
        return self::from([
            'view' => $view,
            'data' => $data,
            'mergeData' => $mergeData,
            'plainText' => true,
        ]);
    }

    /**
     * Crée une instance en HTML directement.
     *
     * @param  string  $view  Nom de la vue
     * @param  StrictAssociative|array<string, mixed>  $data  Données passées à la vue
     * @param  StrictAssociative|array<string, mixed>  $mergeData  Données fusionnées avec la vue
     */
    public static function html(
        string $view,
        StrictAssociative|array $data = [],
        StrictAssociative|array $mergeData = [],
    ): self {
        return self::from([
            'view' => $view,
            'data' => $data,
            'mergeData' => $mergeData,
            'plainText' => false,
        ]);
    }
}
