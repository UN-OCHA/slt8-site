<?php

namespace Drupal\Tests\slt_general\ExistingSite;

use Drupal\node\Entity\Node;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test homepage for anonymous.
 */
class HomePageTest extends ExistingSiteBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Check if the node 1 already exists and create it if not.
    $node = Node::load(1);
    if (empty($node)) {
      $node = $this->createNode([
        'nid' => 200,
        'type' => 'public_page',
        'title' => 'Login Required',
        'status' => 1,
        'path' => [
          'alias' => '/welcome',
          'pathauto' => FALSE,
        ],
      ]);
    }
  }

  /**
   * Test home.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testHomePage() {
    $this->drupalGet('/welcome');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Saving Lives Together');
  }

}
