<?php

namespace Drupal\layout_builder_styles\Form;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\layout_builder_styles\LayoutBuilderStyleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LayoutBuilderStyleForm.
 */
class LayoutBuilderStyleForm extends EntityForm implements ContainerInjectionInterface {

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a LayoutBuilderStyleForm object.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   The block manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(BlockManagerInterface $blockManager, MessengerInterface $messenger) {
    $this->blockManager = $blockManager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.block'), $container->get('messenger'));
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\layout_builder_styles\LayoutBuilderStyleInterface $style */
    $style = $this->entity;

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $style->label(),
      '#required' => TRUE,
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $style->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\layout_builder_styles\Entity\LayoutBuilderStyle::load',
      ),
      '#disabled' => !$style->isNew(),
    );

    $form['classes'] = array(
      '#title' => t('CSS classes'),
      '#type' => 'textarea',
      '#default_value' => $style->getClasses(),
      '#description' => $this->t('Enter one per line.'),
      '#required' => TRUE,
    );

    // For now we only support block styles.
    $form['type'] = [
      '#title' => $this->t('Type'),
      '#type' => 'radios',
      '#default_value' => $style->getType(),
      '#description' => $this->t('Determines if this style applies to sections or blocks.'),
      '#required' => TRUE,
      '#options' => [
        LayoutBuilderStyleInterface::TYPE_COMPONENT => $this->t('Block'),
        LayoutBuilderStyleInterface::TYPE_SECTION => $this->t('Section'),
      ],
    ];

    $blockDefinitions = $this->blockManager->getDefinitions();
    $blockDefinitions = $this->blockManager->getGroupedDefinitions($blockDefinitions);

    // Relabel the inline block type listing as generic "Custom block types".
    // This category will apply to inline blocks & reusable blocks.
    $blockDefinitions['Custom block types'] = $blockDefinitions['Inline blocks'];
    unset($blockDefinitions['Inline blocks']);
    ksort($blockDefinitions);

    $form['block_restrictions'] = [
      '#type' => 'details',
      '#title' => $this->t('Block restrictions'),
      '#description' => $this->t('Optionally limit this style to the following blocks.'),
      '#states' => [
        'visible' => [
          'input[name="type"]' => ['value' => LayoutBuilderStyleInterface::TYPE_COMPONENT],
        ],
      ],
    ];

    foreach ($blockDefinitions as $category => $blocks) {
      $category_form = [
        '#type' => 'fieldset',
        '#title' => $category,
      ];
      foreach ($blocks as $blockId => $block) {
        $machine_name = $blockId;
        $category_form[$blockId] = [
          '#type' => 'checkbox',
          '#title' => $block['admin_label'] . ' <small>(' . $machine_name . ')</small>',
          '#default_value' => in_array($blockId, $style->getBlockRestrictions()),
          '#parents' => [
            'block_restrictions',
            $blockId,
          ],
        ];
        if ($category == 'Custom block types') {
          $machine_name = str_replace('inline_block:', '', $machine_name);
          $category_form[$blockId]['#title'] = $block['admin_label'] . ' <small>(' . $machine_name . ')</small>';
          $category_form[$blockId]['#description'] = $this->t('Block type selections effect both re-usable and inline blocks.');
        }
      }
      $form['block_restrictions'][$category] = $category_form;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = parent::buildEntity($form, $form_state);

    // We need to convert the individual checkbox values that were submitted
    // in the form to a single array containing all the block plugin IDs that
    // were checked.
    $blockRestrictions = $form_state->getValue('block_restrictions');
    $blockRestrictions = array_keys(array_filter($blockRestrictions));
    $entity->set('block_restrictions', $blockRestrictions);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $style = $this->entity;
    $status = $style->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger->addStatus($this->t('Created the %label style.', [
          '%label' => $style->label(),
        ]));
        break;

      default:
        $this->messenger->addStatus($this->t('Saved the %label style.', [
          '%label' => $style->label(),
        ]));
    }
    $form_state->setRedirectUrl($style->toUrl('collection'));
  }

}
