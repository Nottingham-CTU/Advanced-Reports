# Generated from Selenium IDE
# Test name: t34 System Query file field
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_34_System_Query_file_field:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_34_System_Query_file_field(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[@id=\"table-proj_table\"][contains(.,'Advanced Reports Test')]")) > 0
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports Test").click()
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports").click()
    self.driver.find_element(By.LINK_TEXT, "Edit reports").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=system_edit\"][href*=\"report_id=system_query\"]").click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "input[name=\"report_saveable\"][value=\"Y\"]")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"][value=\"Save Report\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"online_designer.php\"]").click()
    self.driver.find_element(By.LINK_TEXT, "Demographics").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[onclick*=\"openAddQuesForm('patient_document\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "[aria-describedby=\"div_add_field\"]")))
    self.driver.execute_script("$('#field_annotation').val(\"@ADVANCED-REPORT-SAVE('system_query')\")")
    self.driver.find_element(By.XPATH, "//*[@aria-describedby=\"div_add_field\"]//button[contains(.,'Save')]").click()
    None if len(elements := self.driver.find_elements(By.CSS_SELECTOR, "[aria-describedby=\"div_add_field\"]")) == 0 else WebDriverWait(self.driver, 30).until(expected_conditions.staleness_of(elements[0]))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"&id=1\"][href*=\"&page=demographics\"]").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.execute_script("dataEntrySubmit('submit-btn-savecontinue')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[onclick*=\"deleteDocumentConfirm\"][onclick*=\"patient_document\"]")) > 0
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports").click()
    self.driver.find_element(By.LINK_TEXT, "Edit reports").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=system_edit\"][href*=\"report_id=system_query\"]").click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "input[name=\"report_saveable\"][value=\"N\"]")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"][value=\"Save Report\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"record_status_dashboard.php\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"&id=1\"][href*=\"&page=demographics\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[onclick*=\"deleteDocumentConfirm\"][onclick*=\"patient_document\"]").click()
    self.driver.find_element(By.XPATH, "//*[@aria-describedby=\"remove-file\"]//button[contains(.,'Yes')]").click()
    None if len(elements := self.driver.find_elements(By.CSS_SELECTOR, "[aria-describedby=\"remove-file\"]")) == 0 else WebDriverWait(self.driver, 30).until(expected_conditions.staleness_of(elements[0]))
    None if len(elements := self.driver.find_elements(By.CSS_SELECTOR, "[aria-describedby=\"file_delete_dialog\"]")) == 0 else WebDriverWait(self.driver, 30).until(expected_conditions.staleness_of(elements[0]))
    self.driver.execute_script("$('#south').remove()")
    self.driver.execute_script("dataEntrySubmit('submit-btn-savecontinue')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.execute_script("$('#south').remove()")
    self.driver.execute_script("dataEntrySubmit('submit-btn-savecontinue')")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[onclick*=\"deleteDocumentConfirm\"][onclick*=\"patient_document\"]")) == 0
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"online_designer.php\"]").click()
    self.driver.find_element(By.LINK_TEXT, "Demographics").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[onclick*=\"openAddQuesForm('patient_document\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "[aria-describedby=\"div_add_field\"]")))
    self.driver.execute_script("$('#field_annotation').val('')")
    self.driver.find_element(By.XPATH, "//*[@aria-describedby=\"div_add_field\"]//button[contains(.,'Save')]").click()
    None if len(elements := self.driver.find_elements(By.CSS_SELECTOR, "[aria-describedby=\"div_add_field\"]")) == 0 else WebDriverWait(self.driver, 30).until(expected_conditions.staleness_of(elements[0]))
