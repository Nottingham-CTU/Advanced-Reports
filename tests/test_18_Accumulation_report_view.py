# Generated from Selenium IDE
# Test name: t18 Accumulation report view
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_18_Accumulation_report_view:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_18_Accumulation_report_view(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[@id=\"table-proj_table\"][contains(.,'Advanced Reports Test')]")) > 0
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports Test").click()
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports").click()
    self.driver.find_element(By.LINK_TEXT, "Accumulation report").click()
    assert self.driver.find_element(By.XPATH, "//table[@id=\"mod-advrep-table\"]//tr[./td[1][contains(.,1)]]/td[2]").text == "1"
    assert self.driver.find_element(By.XPATH, "//table[@id=\"mod-advrep-table\"]//tr[./td[1][contains(.,1)]]/td[3]").text == "2"
    assert self.driver.find_element(By.XPATH, "//table[@id=\"mod-advrep-table\"]//tr[./td[1][contains(.,2)]]/td[2]").text == "0"
    assert self.driver.find_element(By.XPATH, "//table[@id=\"mod-advrep-table\"]//tr[./td[1][contains(.,2)]]/td[3]").text == "2"
    assert self.driver.find_element(By.XPATH, "//table[@id=\"mod-advrep-table\"]/tfoot/tr/td[2]").text == "1"
    assert self.driver.find_element(By.XPATH, "//table[@id=\"mod-advrep-table\"]/tfoot/tr/td[3]").text == "4"
