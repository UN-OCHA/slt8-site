<?php

namespace Drupal\Tests\slt_general\ExistingSite;

use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test homepage for anonymous.
 */
class HomePageTest extends ExistingSiteBase {

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
