@tool @tool_mdocs @javascript
Feature: Find the documentation for the page I am on
  In order to get help where I need it
  As somebody working in Moodle
  I need the documentation for a page to be offered on that page

  # Pages are reached by URL rather than through the site administration tree:
  # under the JavaScript driver that tree lives in a collapsed drawer and its
  # links are not interactable.
  #
  # The Documentation Center's own guides live in its docs/ folder, and so belong
  # to its own pages. That makes it the one plugin whose contextual help can be
  # exercised on a bare Moodle, which is all CI has. The teacher's half of this —
  # that a teacher holds the capability inside their course and nowhere else — is
  # asserted in contextual_test.php, where a course and a role can be built.
  Background:
    Given the following config values are set as admin:
      | includefilenames |         | tool_mdocs |
      | includefolders | docs    | tool_mdocs |
    And I log in as "admin"

  Scenario: A page with documentation offers it behind a help icon
    When I visit "/admin/tool/mdocs/index.php"
    Then "[data-mdocs-region='help']" "css_element" should exist

  Scenario: Opening the help icon lists the documents, and reads one without leaving the page
    When I visit "/admin/tool/mdocs/index.php"
    And I click on "[data-mdocs-region='help'] .popover-region-toggle" "css_element"
    # Scoped to the popover: this page happens to list the same documents, so an
    # unscoped assertion would pass without the help icon working at all.
    Then I should see "Documentation Center — user guide" in the "[data-mdocs-region='help']" "css_element"
    When I click on "Documentation Center — user guide" "button" in the "[data-mdocs-region='help']" "css_element"
    # Rendered in place, so the reader never leaves the page they needed help with.
    Then I should see "Where to find it" in the "[data-region='mdocs-help-doc']" "css_element"

  Scenario: The document is read in a window that can be closed and popped out
    When I visit "/admin/tool/mdocs/index.php"
    And I click on "[data-mdocs-region='help'] .popover-region-toggle" "css_element"
    And I click on "Documentation Center — user guide" "button" in the "[data-mdocs-region='help']" "css_element"
    Then "[data-region='mdocs-help-doc']" "css_element" should be visible
    # A real link to a real page, so that a browser which blocks the pop-up still
    # opens the document rather than doing nothing at all.
    And the "href" attribute of "[data-region='mdocs-help-doc'] [data-action='popout']" "css_element" should contain "/admin/tool/mdocs/help.php"
    When I click on "[data-region='mdocs-help-doc'] [data-action='close-window']" "css_element"
    Then "[data-region='mdocs-help-doc']" "css_element" should not be visible

  Scenario: A page with no documentation offers no help icon at all
    When I visit "/admin/tool/mdocs/index.php"
    And "[data-mdocs-region='help']" "css_element" should exist
    # Nothing ships documentation for the front page, so there is nothing to say.
    And I am on site homepage
    Then "[data-mdocs-region='help']" "css_element" should not exist
