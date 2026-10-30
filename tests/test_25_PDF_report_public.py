# Generated from Selenium IDE
# Test name: t25 PDF report public
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_25_PDF_report_public:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_25_PDF_report_public(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[@id=\"table-proj_table\"][contains(.,'Advanced Reports Test')]")) > 0
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports Test").click()
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports").click()
    self.driver.find_element(By.LINK_TEXT, "Edit reports").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=pdf_edit\"][href*=\"report_id=pdf\"]").click()
    self.driver.execute_script("var vURLs = $('.field-desc').text().match(/https?:\\/\\/\\S*/g);for(var i=0;i<vURLs.length;i++){ if(vURLs[i].indexOf('page=public')>-1) sessionStorage.setItem('publicurl',vURLs[i]); }")
    self.driver.execute_script("$.get(sessionStorage.getItem('publicurl')).always(function(o){if(o.status==400){document.body.setAttribute('data-pub-ok','1')};document.body.setAttribute('data-pub','1')} )")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "body[data-pub]")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "body[data-pub-ok]")) > 0
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "input[name=\"report_as_public\"][value=\"Y\"]")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"][value=\"Save Report\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=instrument_edit\"][href*=\"report_id=instrument_query\"]").click()
    self.driver.execute_script("$.get(sessionStorage.getItem('publicurl')).done(function(d,s,o){if(o.status==200){document.body.setAttribute('data-pub-ok','1')};document.body.setAttribute('data-pub','1')} )")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "body[data-pub]")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "body[data-pub-ok]")) > 0
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "input[name=\"report_as_public\"][value=\"N\"]")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"][value=\"Save Report\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
