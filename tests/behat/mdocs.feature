@tool @tool_mdocs
Feature: Browse plugin documentation in the Documentation Center
  In order to read the documentation shipped inside plugins
  As an administrator
  I need the Documentation Center to discover and display Markdown files

  Background:
    Given the following config values are set as admin:
      | inclusionmode    | filenames | tool_mdocs |
      | includefilenames | readme.md | tool_mdocs |

  @javascript
  Scenario: An administrator opens the Documentation Center and reads a document
    Given I log in as "admin"
    When I navigate to "Documentation Center > Browse documentation" in site administration
    Then I should see "document(s)"
    And I should see "Documentation Center"
    When I follow "Documentation Center (tool_mdocs)"
    Then I should see "Markdown documentation shipped"
    And I should see "On this page"

  Scenario: The Documentation Center reports when nothing matches the rules
    Given the following config values are set as admin:
      | inclusionmode | folders    | tool_mdocs |
      | includefolders | zzznotreal | tool_mdocs |
    And I log in as "admin"
    When I navigate to "Documentation Center > Browse documentation" in site administration
    Then I should see "No Markdown files matched"
