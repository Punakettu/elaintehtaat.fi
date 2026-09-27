<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Routing\RouteMatch;
use Drupal\elaintehtaat\Controller\MediaPageController;
use Drupal\elaintehtaat\Entity\Album;
use Drupal\elaintehtaat\Entity\Image;
use Drupal\elaintehtaat\Hook\FallbackLanguageHooks;
use Drupal\elaintehtaat\Language\FallbackLanguage;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\elaintehtaat\Traits\ContentModelTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

/**
 * Tests the lang attribute on content shown in place of a missing translation.
 *
 * The page is in English, the site's default language in a kernel test, and
 * the content is Finnish unless a test adds an English translation.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class FallbackLanguageTest extends KernelTestBase {

  use ContentModelTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    ...self::CONTENT_MODEL_MODULES,
    'language',
    'content_translation',
    // The media page shows the image in a responsive image style.
    'breakpoint',
    'responsive_image',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installContentModel();
    $this->installConfig(['language']);
    // Saving a node translation rebuilds the node access records.
    $this->installSchema('node', ['node_access']);
    ConfigurableLanguage::createFromLangcode('fi')->save();

    $translation_manager = $this->container->get('content_translation.manager');
    $translation_manager->setEnabled('node', Album::BUNDLE, TRUE);
    $translation_manager->setEnabled('media', Image::BUNDLE, TRUE);
    // Enabling translation adds content_translation's fields to the entity
    // types, which the tables installed above do not have yet.
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $field_manager = $this->container->get('entity_field.manager');
    $field_manager->clearCachedFieldDefinitions();
    $update_manager = $this->container->get('entity.definition_update_manager');
    foreach (['node', 'media'] as $entity_type_id) {
      foreach ($field_manager->getFieldStorageDefinitions($entity_type_id) as $name => $definition) {
        if ($definition->getProvider() === 'content_translation') {
          $update_manager->installFieldStorageDefinition($name, $entity_type_id, 'content_translation', $definition);
        }
      }
    }

    EntityViewMode::create(['id' => 'media.tile', 'label' => 'Tile', 'targetEntityType' => 'media'])->save();
    $display_repository = $this->container->get(EntityDisplayRepositoryInterface::class);
    $display_repository->getViewDisplay('media', Image::BUNDLE, 'tile')
      ->setComponent('field_caption', ['type' => 'text_default'])
      ->save();

    $this->setUpCurrentUser(permissions: ['access content', 'view media']);
  }

  /**
   * The text fields of a Finnish-only album on an English page are marked.
   */
  public function testFallbackFieldsGetLang(): void {
    $album = $this->createAlbum(['title' => 'Kettutarha', 'langcode' => 'fi']);

    // The full view mode leaves the title to the page title.
    $build = $this->buildEntity($album, 'teaser');

    $this->assertSame('fi', $build['title']['#attributes']['lang']);
    $this->assertContains(FallbackLanguage::CACHE_CONTEXT, $build['#cache']['contexts']);
    // The author and the date are translatable too, but not text.
    $this->assertArrayNotHasKey('#attributes', $build['uid']);
    $this->assertArrayNotHasKey('#attributes', $build['created']);
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertMatchesRegularExpression('/<[^>]+lang="fi"[^>]*>\s*Kettutarha/', $html);
  }

  /**
   * An untranslatable field is language neutral and gets no attribute.
   */
  public function testUntranslatableFieldsGetNoLang(): void {
    $project = $this->createProject();
    $album = $this->createAlbum(['langcode' => 'fi', 'field_project' => $project]);
    $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('node', Album::BUNDLE)
      ->setComponent('field_project', ['type' => 'entity_reference_label'])
      ->save();

    $build = $this->buildEntity($album);

    $this->assertArrayHasKey('field_project', $build);
    $this->assertArrayNotHasKey('#attributes', $build['field_project']);
  }

  /**
   * An album with a translation in the page's language is not marked.
   */
  public function testTranslatedEntityGetsNoLang(): void {
    $album = $this->createAlbum(['title' => 'Kettutarha', 'langcode' => 'fi']);
    $album->addTranslation('en', ['title' => 'Fox farm'])->save();

    $build = $this->buildEntity($album);

    $this->assertSame('en', $build['#node']->language()->getId());
    $this->assertArrayNotHasKey('lang', $build['title']['#attributes'] ?? []);
    // The context is there either way, so a cached build cannot leak between
    // languages.
    $this->assertContains(FallbackLanguage::CACHE_CONTEXT, $build['#cache']['contexts']);
  }

  /**
   * An album in the page's own language is not marked.
   */
  public function testEntityInPageLanguageGetsNoLang(): void {
    $album = $this->createAlbum(['title' => 'Fox farm', 'langcode' => 'en']);

    $build = $this->buildEntity($album);

    $this->assertArrayNotHasKey('lang', $build['title']['#attributes'] ?? []);
  }

  /**
   * The page title of a Finnish-only entity is marked on its own page.
   */
  public function testPageTitle(): void {
    $finnish = $this->createAlbum(['langcode' => 'fi']);
    $english = $this->createAlbum(['langcode' => 'en']);
    $media = $this->createImageMedia('Kuva', ['langcode' => 'fi']);
    $route = new Route('/node/{node}');

    $variables = $this->preprocessPageTitle(new RouteMatch('entity.node.canonical', $route, ['node' => $finnish]));
    $this->assertSame('fi', $variables['title_attributes']['lang']);

    $variables = $this->preprocessPageTitle(new RouteMatch('entity.node.canonical', $route, ['node' => $english]));
    $this->assertArrayNotHasKey('lang', $variables['title_attributes']);

    $variables = $this->preprocessPageTitle(new RouteMatch('elaintehtaat.media_page', new Route('/node/{node}/media/{media}'), [
      'node' => $english,
      'media' => $media,
    ]));
    $this->assertSame('fi', $variables['title_attributes']['lang']);

    $variables = $this->preprocessPageTitle(new RouteMatch('user.login', new Route('/user/login')));
    $this->assertArrayNotHasKey('lang', $variables['title_attributes']);
  }

  /**
   * The album link on a tile is marked when the album is only in Finnish.
   */
  public function testTileAlbumLink(): void {
    $media = $this->createImageMedia('Kuva', [
      'langcode' => 'fi',
      'field_caption' => ['value' => 'Kettu häkissä.', 'format' => 'plain_text'],
    ]);
    $this->createAlbum(['title' => 'Kettutarha', 'langcode' => 'fi', 'field_album_media' => [$media]]);

    $build = $this->buildEntity($media, 'tile');

    $this->assertSame('fi', $build['album']['#attributes']['lang']);
    $this->assertSame('fi', $build['field_caption']['#attributes']['lang']);
  }

  /**
   * The media page marks the caption, album title and project title.
   */
  public function testMediaPage(): void {
    $media = $this->createImageMedia('Kuva', [
      'langcode' => 'fi',
      'field_caption' => ['value' => 'Kettu häkissä.', 'format' => 'plain_text'],
    ]);
    $project = $this->createProject(['langcode' => 'en']);
    $album = $this->createAlbum([
      'title' => 'Kettutarha',
      'langcode' => 'fi',
      'field_project' => $project,
      'field_album_media' => [$media],
    ]);
    $controller = $this->container->get('class_resolver')->getInstanceFromDefinition(MediaPageController::class);

    $build = $controller->build($album, $media);

    $this->assertSame('fi', $build['#props']['album_lang']);
    $this->assertSame('', $build['#props']['project_lang']);
    $this->assertSame('fi', $build['#slots']['caption']['#attributes']['lang']);
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('<span class="media-page__album-title" lang="fi">Kettutarha</span>', $html);
    $this->assertStringContainsString('<span class="media-page__album-project faint">Fur farming</span>', $html);
  }

  /**
   * Builds an entity's render array the way the page would render it.
   */
  private function buildEntity(EntityInterface $entity, string $view_mode = 'full'): array {
    $view_builder = $this->container->get('entity_type.manager')->getViewBuilder($entity->getEntityTypeId());
    $this->assertInstanceOf(EntityViewBuilder::class, $view_builder);
    return $view_builder->build($view_builder->view($entity, $view_mode));
  }

  /**
   * Runs the page title preprocess hook on the page of a route.
   */
  private function preprocessPageTitle(RouteMatch $route_match): array {
    $hooks = new FallbackLanguageHooks(
      $this->container->get(FallbackLanguage::class),
      $this->container->get(EntityRepositoryInterface::class),
      $route_match,
    );
    $variables = ['title_attributes' => []];
    $hooks->preprocessPageTitle($variables);
    return $variables;
  }

}
