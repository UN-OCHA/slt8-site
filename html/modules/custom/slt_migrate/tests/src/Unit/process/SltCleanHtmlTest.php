<?php

/**
 * @file
 * Contains \Drupal\Tests\slt_migrate\Unit\process\SltCleanHtmlTest.
 */

namespace Drupal\Tests\slt_migrate\Unit\process;

use Drupal\migrate\MigrateException;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;
use Drupal\slt_migrate\Plugin\migrate\process\SltCleanHtml;

/**
 * Tests the SLT Clean HTML process plugin.
 *
 * @group migrate
 */
class SltCleanHtmlTest extends MigrateProcessTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->plugin = new TextSltCleanHtml();
    parent::setUp();
  }

  /**
   * Test SLT clean HTML fixes heading hierarchy.
   */
  public function testHeadingHierarchyFix() {
    $html = <<<EOT
<h2>Level 2</h2>
<h4>Level 4</h4>
EOT;

    $expected = <<<EOT
<h5>Level 2</h5>
<h6>Level 4</h6>
EOT;

    $this->plugin->setHeadingOffset(4);
    $value = $this->plugin->transform($html, $this->migrateExecutable, $this->row, 'destinationproperty');
    $this->assertSame(trim($expected), trim($value));
  }

  /**
   * Test SLT clean HTML removes empty nodes.
   */
  public function testEmptyNodeRemoval() {
    $html = <<<EOT
<h2><br/>
</h2>
<p> </p>
<p> </p>
<p>not empty</p>
EOT;

    $expected = <<<EOT
<p>not empty</p>
EOT;

    $value = $this->plugin->transform($html, $this->migrateExecutable, $this->row, 'destinationproperty');
    $this->assertSame(trim($expected), trim($value));
  }

  /**
   * Test SLT clean HTML removes unncessary strong tags.
   */
  public function testCleanHtmlStrongRemoval() {
    $html = <<<EOT
<h2><strong>Level 2</strong></h2>
<table><thead><tr><th><strong>Table header</strong></th></tr></thead></table>
EOT;

    $expected = <<<EOT
<h2>Level 2</h2>
<table>
<thead><tr><th>Table header</th></tr></thead>
<tbody></tbody>
</table>
EOT;

    $value = $this->plugin->transform($html, $this->migrateExecutable, $this->row, 'destinationproperty');
    $this->assertSame(trim($expected), trim($value));
  }

  /**
   * Test SLT clean HTML removes unwanted line breaks.
   */
  public function testLineBreakRemoval() {
    $html = <<<EOT
<h2><br/>
Level 2</h2>
<h3><br/>
Past Chairmanship Workplans,<br />
Meeting Schedules &amp; Summaries</h3>
EOT;

    $expected = <<<EOT
<h2>Level 2</h2>
<h3>Past Chairmanship Workplans, Meeting Schedules &amp; Summaries</h3>
EOT;

    $value = $this->plugin->transform($html, $this->migrateExecutable, $this->row, 'destinationproperty');
    $this->assertSame(trim($expected), trim($value));
  }

  /**
   * Test SLT clean HTML fixes tables.
   */
  public function testTableFix() {
    $html = <<<EOT
<h2>OIOS Audits</h2>

<table border="0" cellpadding="5" cellspacing="5" width="95%">
  <tbody>
    <tr>
      <td class="docheaderbkg">2017</td>
    </tr>
    <tr>
      <td class="docbkg"><a href="/system/files/OIOS%20Report%20-%20CERF%20for%20Ethiopia%202017_065.pdf" target="_blank">OIOS Report - CERF for Ethiopia 2017</a></td>
    </tr>
    <tr>
      <td class="docbkg"><a href="/system/files/OiOs%20report%202017.pdf">OIOS Report - OCHA for Coordination &amp; Response Function 2017</a></td>
    </tr>
    <tr>
      <td class="docbkg"><a href="/system/files/OIOS%20Report%20-%20OCHA%20%20for%20Ethiopia%202017_014.pdf" target="_blank">OIOS Report - OCHA for Ethiopia 2017</a></td>
    </tr>
    <tr>
      <td class="docbkg"><a href="/system/files/OIOS%20Report%20-%20OCHA%20%20for%20Mali%202017_059.pdf" target="_blank">OIOS Report - OCHA for Mali 2017</a></td>
    </tr>
    <tr>
      <td class="docheaderbkg">2016</td>
    </tr>
    <tr>
      <td class="docbkg"><a href="/system/files/OIOS%20audit%20of%20CAR%202016.pdf" target="_blank">OIOS Report - OCHA Operations for CAR 2016</a></td>
    </tr>
    <tr>
      <td class="docbkg"><a href="/system/files/OIOS%20audit%20of%20Human%20resources%202016_0.pdf" target="_blank">OIOS Report - OCHA Human Resources 2016</a></td>
    </tr>
    <tr>
      <td class="docbkg"><a href="/system/files/OIOS%20audit%20of%20resource%20mobilization%202016.pdf" target="_blank">OIOS Report - Resource Mobilization 2016</a></td>
    </tr>
    <tr>
      <td class="docbkg"><a href="/system/files/OIOS%20audit%20Sudan%202016_0.pdf" target="_blank">OIOS Report - Sudan 2016</a></td>
    </tr>
    <tr>
      <td class="docbkg"><a href="/system/files/OIOS%20audit%20Syria%20Operation%202016.pdf" target="_blank">OIOS Report Syria Operations 2016</a></td>
    </tr>
    <tr>
      <td class="docbkg"><a href="/system/files/OIOS%20South%20Sudan%20CHF%202016_0.pdf" target="_blank">OIOS Report South Sudan 2016</a></td>
    </tr>
  </tbody>
</table>
EOT;

    $expected = <<<EOT
<h2>OIOS Audits</h2>
<table>
<thead><tr><th>2017</th></tr></thead>
<tbody>
<tr><td><a href="/system/files/OIOS%20Report%20-%20CERF%20for%20Ethiopia%202017_065.pdf" target="_blank" rel="noreferrer noopener">OIOS Report - CERF for Ethiopia 2017</a></td></tr>
<tr><td><a href="/system/files/OiOs%20report%202017.pdf">OIOS Report - OCHA for Coordination &amp; Response Function 2017</a></td></tr>
<tr><td><a href="/system/files/OIOS%20Report%20-%20OCHA%20%20for%20Ethiopia%202017_014.pdf" target="_blank" rel="noreferrer noopener">OIOS Report - OCHA for Ethiopia 2017</a></td></tr>
<tr><td><a href="/system/files/OIOS%20Report%20-%20OCHA%20%20for%20Mali%202017_059.pdf" target="_blank" rel="noreferrer noopener">OIOS Report - OCHA for Mali 2017</a></td></tr>
</tbody>
</table>
<table>
<thead><tr><th>2016</th></tr></thead>
<tbody>
<tr><td><a href="/system/files/OIOS%20audit%20of%20CAR%202016.pdf" target="_blank" rel="noreferrer noopener">OIOS Report - OCHA Operations for CAR 2016</a></td></tr>
<tr><td><a href="/system/files/OIOS%20audit%20of%20Human%20resources%202016_0.pdf" target="_blank" rel="noreferrer noopener">OIOS Report - OCHA Human Resources 2016</a></td></tr>
<tr><td><a href="/system/files/OIOS%20audit%20of%20resource%20mobilization%202016.pdf" target="_blank" rel="noreferrer noopener">OIOS Report - Resource Mobilization 2016</a></td></tr>
<tr><td><a href="/system/files/OIOS%20audit%20Sudan%202016_0.pdf" target="_blank" rel="noreferrer noopener">OIOS Report - Sudan 2016</a></td></tr>
<tr><td><a href="/system/files/OIOS%20audit%20Syria%20Operation%202016.pdf" target="_blank" rel="noreferrer noopener">OIOS Report Syria Operations 2016</a></td></tr>
<tr><td><a href="/system/files/OIOS%20South%20Sudan%20CHF%202016_0.pdf" target="_blank" rel="noreferrer noopener">OIOS Report South Sudan 2016</a></td></tr>
</tbody>
</table>
EOT;

    $value = $this->plugin->transform($html, $this->migrateExecutable, $this->row, 'destinationproperty');
    $this->assertSame(trim($expected), trim($value));
  }

  /**
   * Test SLt clean HTML fails properly on non-arrays.
   */
  public function testWithNonArray() {
    $this->expectException(MigrateException::class);
    $this->plugin->transform(['foo'], $this->migrateExecutable, $this->row, 'destinationproperty');
  }

}

/**
 * Test class for SLT Clean HTML process.
 */
class TextSltCleanHtml extends SltCleanHtml {

  /**
   * {@inheritdoc}
   */
  public function __construct() {
  }

  /**
   * Set the heading level offset.
   *
   * @param string $heading_offset
   *   The new heading level offset.
   */
  public function setHeadingOffset($heading_offset) {
    $this->configuration['heading_offset'] = $heading_offset;
  }

}
