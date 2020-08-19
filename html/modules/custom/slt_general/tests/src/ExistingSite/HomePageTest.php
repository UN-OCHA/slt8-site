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
    $this->drupalGet('/login-required');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->pageTextContains('Login Required');
    $this->assertSession()->pageTextContains('Welcome to the Saving Lives Together (SLT) website.');
  }

}
