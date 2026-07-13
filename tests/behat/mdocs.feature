@tool @tool_mdocs
Feature: Browse plugin documentation in the Documentation Center
  In order to read the documentation shipped inside plugins
  As an administrator
  I need the Documentation Center to discover and display Markdown files

  Background:
    Given the following config values are set as admin:
      | inclusionmode    | filenames | tool_mdocs |
      | includefilenames | readme.md | tool_mdocs |
    And I log in as "admin"

  Scenario: An administrator opens the Documentation Center and reads a document
    When I navigate to "Documentation Center > Browse documentation" in site administration
    Then I should see "document(s)"
    And I should see "Documentation Center"
    When I follow "Documentation Center (tool_mdocs)"
    Then I should see "Markdown documentation shipped"
    # The table of contents is published as a fake block in the block drawer.
    And I should see "On this page"

  Scenario: The Documentation Center reports when nothing matches the rules
    Given the following config values are set as admin:
      | inclusionmode  | folders    | tool_mdocs |
      | includefolders | zzznotreal | tool_mdocs |
    When I navigate to "Documentation Center > Browse documentation" in site administration
    Then I should see "No Markdown files matched"

  @javascript
  Scenario: Searching matches text inside a document, not just its title
    When I visit "/admin/tool/mdocs/index.php"
    # "mkdocs" appears only in the body of this plugin's readme, never in a
    # title, component name or path, so a hit proves the content is indexed.
    And I set the field "Search documentation" to "mkdocs"
    Then I should see "mkdocs" in the "#tool-mdocs-search-results" "css_element"
    And I should see "Documentation Center" in the "#tool-mdocs-search-results" "css_element"
