# Generated from Selenium IDE
# Test name: t36 Word Document report view/download
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_36_Word_Document_report_view_download:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_36_Word_Document_report_view_download(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[@id=\"table-proj_table\"][contains(.,'Advanced Reports Test')]")) > 0
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports Test").click()
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports").click()
    self.driver.find_element(By.LINK_TEXT, "Word Document Report").click()
    # Check that the Word document is rendered correctly
    # The report should download as a .docx file
    # We can't easily test the actual file download in Selenium, but we can verify the page loads
    # and the download link is present
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"download=1\"]")) > 0
    # Test download by clicking the download link
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"download=1\"]").click()
    # Wait for download to start (we can't easily verify the file content in Selenium)
    time.sleep(3)
    # Test view access for non-admin user
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.ID, "impersonate-user-select").find_element(By.CSS_SELECTOR, "*[value='user']").click()
    self.driver.find_element(By.XPATH, "//div[contains(@aria-describedby,'popup')]//button[contains(.,'Close')]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports").click()
    self.driver.find_element(By.LINK_TEXT, "Word Document Report").click()
    # Should be accessible since user has view rights
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"download=1\"]")) > 0
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.ID, "impersonate-user-select").find_element(By.XPATH, "(descendant::option)[1]").click()
    self.driver.find_element(By.XPATH, "//div[contains(@aria-describedby,'popup')]//button[contains(.,'Close')]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))