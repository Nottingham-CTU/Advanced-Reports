# Generated from Selenium IDE
# Test name: t17 Create/edit Accumulation report
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_17_Create_edit_Accumulation_report:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_17_Create_edit_Accumulation_report(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[@id=\"table-proj_table\"][contains(.,'Advanced Reports Test')]")) > 0
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports Test").click()
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports").click()
    self.driver.find_element(By.LINK_TEXT, "Edit reports").click()
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "select[name=\"report_type\"] option[value=\"accumulation\"]")) > 0
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.ID, "impersonate-user-select").find_element(By.CSS_SELECTOR, "*[value='user']").click()
    self.driver.find_element(By.XPATH, "//div[contains(@aria-describedby,'popup')]//button[contains(.,'Close')]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "select[name=\"report_type\"] option[value=\"accumulation\"]")) > 0
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.ID, "impersonate-user-select").find_element(By.XPATH, "(descendant::option)[1]").click()
    self.driver.find_element(By.XPATH, "//div[contains(@aria-describedby,'popup')]//button[contains(.,'Close')]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.NAME, "report_id").click()
    self.driver.find_element(By.NAME, "report_id").send_keys("accumulation_report")
    self.driver.find_element(By.NAME, "report_label").click()
    self.driver.find_element(By.NAME, "report_label").send_keys("Accumulation report")
    self.driver.find_element(By.NAME, "report_type").find_element(By.CSS_SELECTOR, "*[value='accumulation']").click()
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"][value=\"Add Report\"]").click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "[name=\"report_visible\"][value=\"Y\"]")).is_selected() else element.click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "[name=\"report_download\"][value=\"Y\"]")).is_selected() else element.click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "[name=\"report_as_image\"][value=\"Y\"]")).is_selected() else element.click()
    self.driver.find_element(By.NAME, "acc_start").click()
    self.driver.find_element(By.NAME, "acc_start").send_keys("1")
    self.driver.find_element(By.NAME, "acc_end").click()
    self.driver.find_element(By.NAME, "acc_end").send_keys("3")
    self.driver.find_element(By.NAME, "acc_logic").click()
    self.driver.find_element(By.NAME, "acc_logic").send_keys("if([record-name] <= [accumulation-number], [accumulation-number], 0)")
    self.driver.find_element(By.NAME, "group_logic").click()
    self.driver.find_element(By.NAME, "group_logic").send_keys("[record-name]")
    None if (element := self.driver.find_element(By.NAME, "group_total")).is_selected() else element.click()
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"][value=\"Save Report\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "a[href*=\"page=view\"][href*=\"report_id=accumulation_report\"]")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"page=accumulation_edit\"][href*=\"report_id=accumulation_report\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[onclick*=\"mod_advrep_copy\"][onclick*=\"accumulation_report\"]")) > 0
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.ID, "impersonate-user-select").find_element(By.CSS_SELECTOR, "*[value='user']").click()
    self.driver.find_element(By.XPATH, "//div[contains(@aria-describedby,'popup')]//button[contains(.,'Close')]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"page=accumulation_edit\"][href*=\"report_id=accumulation_report\"]")) > 0
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[onclick*=\"mod_advrep_copy\"][onclick*=\"accumulation_report\"]")) > 0
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.ID, "impersonate-user-select").find_element(By.XPATH, "(descendant::option)[1]").click()
    self.driver.find_element(By.XPATH, "//div[contains(@aria-describedby,'popup')]//button[contains(.,'Close')]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
