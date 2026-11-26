# Generated from Selenium IDE
# Test name: t35 Create/edit Word Document report
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_35_Create_edit_Word_Document_report:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_35_Create_edit_Word_Document_report(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[@id=\"table-proj_table\"][contains(.,'Advanced Reports Test')]")) > 0
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports Test").click()
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports").click()
    self.driver.find_element(By.LINK_TEXT, "Edit reports").click()
    print("- Check Word Document report create rights")
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "select[name=\"report_type\"] option[value=\"docx\"]")) > 0
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.ID, "impersonate-user-select").find_element(By.CSS_SELECTOR, "*[value='user']").click()
    self.driver.find_element(By.XPATH, "//div[contains(@aria-describedby,'popup')]//button[contains(.,'Close')]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    # Word Document reports should be editable by users with report edit rights
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "select[name=\"report_type\"] option[value=\"docx\"]")) > 0
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.ID, "impersonate-user-select").find_element(By.XPATH, "(descendant::option)[1]").click()
    self.driver.find_element(By.XPATH, "//div[contains(@aria-describedby,'popup')]//button[contains(.,'Close')]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.NAME, "report_id").click()
    self.driver.find_element(By.NAME, "report_id").send_keys("docx_report")
    self.driver.find_element(By.NAME, "report_label").click()
    self.driver.find_element(By.NAME, "report_label").send_keys("Word Document Report")
    self.driver.find_element(By.NAME, "report_type").find_element(By.CSS_SELECTOR, "*[value='docx']").click()
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"][value=\"Add Report\"]").click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "[name=\"report_visible\"][value=\"Y\"]")).is_selected() else element.click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "[name=\"report_download\"][value=\"Y\"]")).is_selected() else element.click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "[name=\"report_as_public\"][value=\"Y\"]")).is_selected() else element.click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "[name=\"report_api\"][value=\"Y\"]")).is_selected() else element.click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "[name=\"report_saveable\"][value=\"Y\"]")).is_selected() else element.click()
    # Select the Instrument Query report as source
    self.driver.find_element(By.NAME, "source").find_element(By.CSS_SELECTOR, "*[value='instrument_query']").click()
    # Add a simple HTML template
    self.driver.execute_script("$('[name=\"docx\"]').val(\"<h1>Test Report</h1><p>Record ID: [record_id]</p><table><tr><th>Field</th><th>Value</th></tr><tr><td>Name</td><td>[name_field]</td></tr></table>\")")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"][value=\"Save Report\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "a[href*=\"page=view\"][href*=\"report_id=docx_report\"]")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"page=docx_edit\"][href*=\"report_id=docx_report\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[onclick*=\"mod_advrep_copy\"][onclick*=\"docx_report\"]")) > 0
    # Test that non-admin user can edit (since Word Document reports are editable by users with report rights)
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.ID, "impersonate-user-select").find_element(By.CSS_SELECTOR, "*[value='user']").click()
    self.driver.find_element(By.XPATH, "//div[contains(@aria-describedby,'popup')]//button[contains(.,'Close')]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"page=docx_edit\"][href*=\"report_id=docx_report\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[onclick*=\"mod_advrep_copy\"][onclick*=\"docx_report\"]")) > 0
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.ID, "impersonate-user-select").find_element(By.XPATH, "(descendant::option)[1]").click()
    self.driver.find_element(By.XPATH, "//div[contains(@aria-describedby,'popup')]//button[contains(.,'Close')]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))