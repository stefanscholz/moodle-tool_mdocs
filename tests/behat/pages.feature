@tool @tool_mdocs @javascript
Feature: Author documentation pages inside the Documentation Center
  In order to document site-specific procedures
  As a manager
  I need to create documentation pages alongside the plugin file docs

  # These go straight to the page by URL rather than clicking through the site
  # administration tree: under the JavaScript driver that tree lives in a
  # collapsed drawer and its links are not interactable.
  Background:
    Given I log in as "admin"

  Scenario: Create a standalone page and read it
    When I visit "/admin/tool/mdocs/index.php"
    And I follow "Add page"
    And I set the field "Title" to "Local onboarding guide"
    And I set the field "Content (Markdown)" to "# Onboarding heading"
    And I press "Save changes"
    Then I should see "Onboarding heading"
    And I should see "Site documentation"

  Scenario: A draft page is flagged and not shown to plain viewers
    When I visit "/admin/tool/mdocs/index.php"
    And I follow "Add page"
    And I set the field "Title" to "Work in progress"
    And I set the field "Content (Markdown)" to "# Draft content"
    And I set the field "Visibility" to "Draft (hidden)"
    And I press "Save changes"
    Then I should see "Draft content"
    And I should see "Draft"

  Scenario: The editor renders a preview of the Markdown before saving
    When I visit "/admin/tool/mdocs/edit.php"
    And I set the field "Content (Markdown)" to "# Preview heading"
    And I press "Preview"
    # Rendered server-side by the same pipeline that renders a saved page.
    Then I should see "Preview heading" in the ".tool-mdocs-editor-preview" "css_element"
