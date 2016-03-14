<?php

/**
 * @file
 * Contains \Drupal\wps_tester\Controller\DefaultController.
 */

namespace Drupal\wps_tester\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\multiversion\Entity\Storage\Sql\NodeStorage;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\multiversion\Entity\Index\SequenceIndex;
use Drupal\multiversion\Entity\Index\RevisionTreeIndex;
use Drupal\multiversion\Entity\Query\Sql\QueryFactory;
use Drupal\multiversion\MultiversionManager;
use Drupal\multiversion\Workspace\WorkspaceManager;

/**
 * Class DefaultController.
 *
 * @package Drupal\wps_tester\Controller
 */
class DefaultController extends ControllerBase {

  /**
   * Drupal\multiversion\Entity\Index\SequenceIndex definition.
   *
   * @var \Drupal\multiversion\Entity\Index\SequenceIndex
   */
  protected $entity_index_sequence;

  /**
   * Drupal\multiversion\Entity\Index\RevisionTreeIndex definition.
   *
   * @var \Drupal\multiversion\Entity\Index\RevisionTreeIndex
   */
  protected $tree;

  /**
   * Drupal\multiversion\Entity\Query\Sql\QueryFactory definition.
   *
   * @var \Drupal\multiversion\Entity\Query\Sql\QueryFactory
   */
  protected $entity_query_sql_multiversion;

  /**
   * Drupal\multiversion\MultiversionManager definition.
   *
   * @var \Drupal\multiversion\MultiversionManager
   */
  protected $multiversion_manager;

  /**
   * Drupal\multiversion\Workspace\WorkspaceManager definition.
   *
   * @var \Drupal\multiversion\Workspace\WorkspaceManager
   */
  protected $workspace_manager;
  /**
   * {@inheritdoc}
   */
  public function __construct(SequenceIndex $entity_index_sequence, RevisionTreeIndex $entity_index_rev_tree, QueryFactory $entity_query_sql_multiversion, MultiversionManager $multiversion_manager, WorkspaceManager $workspace_manager, EntityTypeManagerInterface $entityTypeManager) {
    $this->entity_index_sequence = $entity_index_sequence;
    $this->tree = $entity_index_rev_tree;
    $this->entity_query_sql_multiversion = $entity_query_sql_multiversion;
    $this->multiversion_manager = $multiversion_manager;
    $this->workspace_manager = $workspace_manager;


  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.index.sequence'),
      $container->get('entity.index.rev.tree'),
      $container->get('entity.query.sql.multiversion'),
      $container->get('multiversion.manager'),
      $container->get('workspace.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Test function to create conflicts.
   *
   * @return string
   *   Test string.
   */
  public function ConflictCreator() {
    $storage = $this->entityTypeManager()->getStorage('node');
    $entity = $storage->create([
      'title' => 'boogey',
      'type' => 'article',
    ]);
    $uuid = $entity->uuid();
    $entity_id = $entity->id();
    $revision_1 = $entity->getRevisionId();

    // Create a conflict scenario to fully test the parsing.

    // Initial revision.
    $entity->save();
    $entity_id = $entity->id();
    $revision_1 = $entity->getRevisionId();
    $revs[] = $entity->_rev->value;


    $entity->save();
    $revision_2 = $entity->getRevisionId();
    $revs[] = $entity->_rev->value;

    $entity->save();

    $revs[] = $leaf_one = $entity->_rev->value;

    $entity = $storage->load($entity_id);

    // Create a new branch from the second revision.
    $entity = $storage->loadRevision($revision_2);
    $entity->save();
    $revision_4 = $entity->getRevisionId();
    $revs[] = $leaf_two = $entity->_rev->value;

    // We now have two leafs at the tip of the tree.
    $leafs = [$leaf_one, $leaf_two];
    sort($leafs);
    $expected_leaf = array_pop($leafs);
    $entity = $storage->load($entity_id);

    // Continue the last branch.
    $entity = $storage->loadRevision($revision_4);
    $entity->save();
    $revs[] = $entity->_rev->value;

    $entity = $this->loadAny($storage,$entity_id);

    // Create a new branch based on the first revision.
    $entity = $storage->loadRevision($revision_1);
    $entity->save();
    $r1 = $entity->getRevisionId();
    $revs[] = $entity->_rev->value;

    // Create a another new branch based on the first revision.
    $entity = $storage->loadRevision($revision_1);
    $entity->save();
    $r2 = $entity->getRevisionId();
    debug("$r1 - $r2", 'revision comp');
    //$storage->delete([$entity]);


    $entity = $this->loadAny($storage,$entity_id);;

    // Sort the expected tree according to the algorithm.


    $tree = $this->tree->getTree($uuid);


    $default_rev = $this->tree->getDefaultRevision($uuid);


    $expected_default_branch = [
      $revs[0] => 'available',
      $revs[1] => 'available',
      $revs[3] => 'available',
      $revs[4] => 'available',
    ];
    $default_branch = $this->tree->getDefaultBranch($uuid);


    $count = $this->tree->countRevs($uuid);

    $expected_open_revision = [
      $revs[2] => 'available',
      $revs[4] => 'available',
      $revs[5] => 'available',
    ];
    $open_revisions = $this->tree->getOpenRevisions($uuid);

    $expected_conflicts = [
      $revs[2] => 'available',
      $revs[5] => 'available',
    ];
    $conflicts = $this->tree->getConflicts($uuid);
    debug($conflicts, 'conflicts');
    debug($this->tree->getTree($uuid), 'tree');
    return [
      '#type' => 'markup',
      '#markup' => $entity->label(),
    ];
  }

  /**
   * Loads an entity whether it is deleted or not.
   *
   * @param \Drupal\multiversion\Entity\Storage\Sql\NodeStorage $storage
   * @param $id
   *   Node Id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  protected function loadAny(NodeStorage $storage, $id) {
    if ($entity = $storage->load($id)) {
      return $entity;
    }
    return $storage->loadDeleted($id);
  }

}
