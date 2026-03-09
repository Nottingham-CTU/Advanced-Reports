# Generated from Selenium IDE
# Test name: t30 System Query view
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_30_System_Query_view:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_30_System_Query_view(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[@id=\"table-proj_table\"][contains(.,'Advanced Reports Test')]")) > 0
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports Test").click()
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports").click()
    self.driver.find_element(By.LINK_TEXT, "System Query").click()
    assert self.driver.find_element(By.CSS_SELECTOR, "#mod-advrep-table thead th[data-colnum=\"0\"]").text == "[e][directory_prefix]"
    assert self.driver.find_element(By.CSS_SELECTOR, "#mod-advrep-table thead th[data-colnum=\"1\"]").text == "[e][key]"
