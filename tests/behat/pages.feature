@tool @tool_mdocs @javascript
Feature: Author documentation pages inside the Documentation Center
  In order to document site-specific procedures
  As a manager
  I need to create documentation pages alongside the plugin file docs

  Background:
    Given I log in as "admin"

  Scenario: Create a standalone page and read it
    When I navigate to "Documentation Center > Browse documentation" in site administration
    And I follow "Add page"
    And I set the field "Title" to "Local onboarding guide"
    And I set the field "Content (Markdown)" to "# Onboarding heading"
    And I press "Save changes"
    Then I should see "Onboarding heading"
    And I should see "Site documentation"

  Scenario: A draft page is flagged and not shown to plain viewers
    When I navigate to "Documentation Center > Browse documentation" in site administration
    And I follow "Add page"
    And I set the field "Title" to "Work in progress"
    And I set the field "Content (Markdown)" to "# Draft content"
    And I set the field "Visibility" to "Draft (hidden)"
    And I press "Save changes"
    Then I should see "Draft content"
    And I should see "Draft"
