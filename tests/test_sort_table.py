from playwright.sync_api import sync_playwright
import sys
import os

# Set working directory to the project root to ensure relative paths work
os.chdir(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

def run_test_sort_table_by_username():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Setup mock HTML table
        html_content = """
        <!DOCTYPE html>
        <html>
        <body>
            <table>
                <thead>
                    <tr>
                        <th class="sortable" data-sort="username">Usuário</th>
                        <th class="sortable" data-sort="nivel_acesso">Nível de Acesso</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Zebra</td><td>admin</td></tr>
                    <tr><td>Apple</td><td>bombeiro</td></tr>
                    <tr><td>Mango</td><td>fornecedor</td></tr>
                </tbody>
            </table>
        </body>
        </html>
        """
        page.set_content(html_content)

        script_path = os.path.join('js', 'table_utils.js')
        with open(script_path, 'r') as f:
            script_content = f.read()
        page.add_script_tag(content=script_content)

        # Initial order: Zebra, Apple, Mango
        rows = page.query_selector_all("table tbody tr")
        assert rows[0].query_selector("td:nth-child(1)").inner_text() == "Zebra"

        # Sort by username
        page.evaluate("sortTable('username')")

        # New order should be: Apple, Mango, Zebra
        rows = page.query_selector_all("table tbody tr")
        assert rows[0].query_selector("td:nth-child(1)").inner_text() == "Apple"
        assert rows[1].query_selector("td:nth-child(1)").inner_text() == "Mango"
        assert rows[2].query_selector("td:nth-child(1)").inner_text() == "Zebra"

        browser.close()
    print("test_sort_table_by_username: PASSED")

def run_test_sort_table_by_access_level():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        html_content = """
        <!DOCTYPE html>
        <html>
        <body>
            <table>
                <tbody>
                    <tr><td>User1</td><td>fornecedor</td></tr>
                    <tr><td>User2</td><td>admin</td></tr>
                    <tr><td>User3</td><td>bombeiro</td></tr>
                </tbody>
            </table>
        </body>
        </html>
        """
        page.set_content(html_content)

        script_path = os.path.join('js', 'table_utils.js')
        with open(script_path, 'r') as f:
            script_content = f.read()
        page.add_script_tag(content=script_content)

        # Sort by nivel_acesso
        page.evaluate("sortTable('nivel_acesso')")

        # New order: admin, bombeiro, fornecedor
        rows = page.query_selector_all("table tbody tr")
        assert rows[0].query_selector("td:nth-child(2)").inner_text() == "admin"
        assert rows[1].query_selector("td:nth-child(2)").inner_text() == "bombeiro"
        assert rows[2].query_selector("td:nth-child(2)").inner_text() == "fornecedor"

        browser.close()
    print("test_sort_table_by_access_level: PASSED")

def run_test_sort_table_empty_or_missing():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Test with no table body
        page.set_content("<html><body></body></html>")
        script_path = os.path.join('js', 'table_utils.js')
        with open(script_path, 'r') as f:
            script_content = f.read()
        page.add_script_tag(content=script_content)

        # Should not throw error
        page.evaluate("sortTable('username')")

        # Test with empty table
        page.set_content("<table><tbody></tbody></table>")
        page.add_script_tag(content=script_content)
        page.evaluate("sortTable('username')")

        browser.close()
    print("test_sort_table_empty_or_missing: PASSED")

if __name__ == "__main__":
    try:
        run_test_sort_table_by_username()
        run_test_sort_table_by_access_level()
        run_test_sort_table_empty_or_missing()
        print("\nAll tests passed successfully!")
    except Exception as e:
        print(f"\nTest failed: {e}")
        sys.exit(1)
