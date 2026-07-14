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
      | inclusionmode  | folders | tool_mdocs |
      | includefolders | docs    | tool_mdocs |
    And I log in as "admin"

  Scenario: A page with documentation offers it behind a help icon
    When I visit "/admin/tool/mdocs/index.php"
    Then "[data-region='mdocs-help']" "css_element" should exist

  Scenario: Opening the help icon lists the documents, and reads one without leaving the page
    When I visit "/admin/tool/mdocs/index.php"
    And I click on "[data-region='mdocs-help'] .popover-region-toggle" "css_element"
    Then I should see "Documentation Center — user guide"
    When I click on "Documentation Center — user guide" "link"
    # Rendered in place, so the reader never leaves the page they needed help with.
    Then I should see "Where to find it"

  Scenario: A page with no documentation offers no help icon at all
    When I visit "/admin/tool/mdocs/index.php"
    And "[data-region='mdocs-help']" "css_element" should exist
    # Nothing ships documentation for the front page, so there is nothing to say.
    And I am on site homepage
    Then "[data-region='mdocs-help']" "css_element" should not exist
