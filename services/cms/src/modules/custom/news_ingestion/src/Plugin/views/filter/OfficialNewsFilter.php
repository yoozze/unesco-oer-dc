<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\news_ingestion\Service\SourcePrerequisiteService;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposed checkbox: show only official OER DC news (field_news_source = oerdc).
 *
 * @ViewsFilter("news_official_only")
 */
final class OfficialNewsFilter extends FilterPluginBase {

    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        private readonly SourcePrerequisiteService $prerequisites,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('news_ingestion.source_prerequisites'),
        );
    }

    public function adminSummary(): string {
        return (string) $this->t('Official OER DC news only');
    }

    public function query(): void {
        if (empty($this->value)) {
            return;
        }

        $term = $this->prerequisites->loadTermByKey('oerdc');
        if (!$term) {
            return;
        }

        $configuration = [
            'type' => 'INNER',
            'table' => 'node__field_news_source',
            'field' => 'entity_id',
            'left_table' => 'node_field_data',
            'left_field' => 'nid',
            'operator' => '=',
        ];
        $join = Views::pluginManager('join')->createInstance('standard', $configuration);
        $alias = $this->query->addTable('node__field_news_source', $this->relationship, $join);
        $this->query->addWhere(
            $this->options['group'],
            "$alias.field_news_source_target_id",
            $term->id(),
            '=',
        );
    }

    protected function valueForm(&$form, FormStateInterface $form_state): void {
        $form['value'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Official news only'),
            '#default_value' => !empty($this->value),
        ];
    }

    public function buildExposedForm(&$form, FormStateInterface $form_state): void {
        $identifier = $this->options['expose']['identifier'] ?? 'official_only';
        $form[$identifier] = [
            '#type' => 'checkbox',
            '#title' => $this->options['expose']['label'] !== ''
                ? $this->t($this->options['expose']['label'])
                : $this->t('Official news only'),
            '#default_value' => !empty($this->value),
        ];
    }

    public function acceptExposedInput($input): bool {
        if (!parent::acceptExposedInput($input)) {
            return FALSE;
        }

        if (!isset($input[$this->options['expose']['identifier']])) {
            $this->value = FALSE;
        } else {
            $this->value = (bool) $input[$this->options['expose']['identifier']];
        }

        return TRUE;
    }
}
