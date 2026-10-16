# Generated from Selenium IDE
# Test name: t21 SQL report public/api
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_21_SQL_report_public_api:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_21_SQL_report_public_api(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[@id=\"table-proj_table\"][contains(.,'Advanced Reports Test')]")) > 0
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports Test").click()
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports").click()
    self.driver.find_element(By.LINK_TEXT, "Edit reports").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=sql_edit\"][href*=\"report_id=sql_report\"]").click()
    self.driver.execute_script("var vURLs = $('.field-desc').text().match(/https?:\\/\\/\\S*/g);for(var i=0;i<vURLs.length;i++){ if(vURLs[i].indexOf('page=api')>-1) sessionStorage.setItem('apiurl',vURLs[i]);if(vURLs[i].indexOf('page=public')>-1) sessionStorage.setItem('publicurl',vURLs[i]); }")
    self.driver.execute_script("$.get(sessionStorage.getItem('publicurl')).always(function(o){if(o.status==400){document.body.setAttribute('data-pub-ok','1')};document.body.setAttribute('data-pub','1')} )")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "body[data-pub]")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "body[data-pub-ok]")) > 0
    self.driver.execute_script("$.get(sessionStorage.getItem('apiurl')).always(function(o){if(o.status==400){document.body.setAttribute('data-api-ok','1')};document.body.setAttribute('data-api','1')} )")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "body[data-api]")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "body[data-api-ok]")) > 0
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "input[name=\"report_as_api\"][value=\"Y\"]")).is_selected() else element.click()
    self.driver.find_element(By.CSS_SELECTOR, "a[onclick*=\"new API key\"]").click()
    self.driver.switch_to.alert.accept()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "input[name=\"report_as_public\"][value=\"Y\"]")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"][value=\"Save Report\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=sql_edit\"][href*=\"report_id=sql_report\"]").click()
    self.driver.execute_script("$.get(sessionStorage.getItem('publicurl')).done(function(d,s,o){if(o.status==200){document.body.setAttribute('data-pub-ok','1')};document.body.setAttribute('data-pub','1')} )")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "body[data-pub]")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "body[data-pub-ok]")) > 0
    self.driver.execute_script("$.post(sessionStorage.getItem('apiurl')).always(function(o){if(o.status==400){document.body.setAttribute('data-api-ok','1')};document.body.setAttribute('data-api','1')} )")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "body[data-api]")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "body[data-api-ok]")) > 0
    self.driver.execute_script("$.post(sessionStorage.getItem('apiurl'),{api_key:'abc123'}).always(function(o){if(o.status==400){document.body.setAttribute('data-api2-ok','1')};document.body.setAttribute('data-api2','1')} )")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "body[data-api2]")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "body[data-api2-ok]")) > 0
    self.driver.execute_script("$.post(sessionStorage.getItem('apiurl'),{api_key:$('input[name=\"report_api_key\"]').val()}).done(function(d,s,o){if(o.status==200){document.body.setAttribute('data-api3-ok','1')};document.body.setAttribute('data-api3','1')} )")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "body[data-api3]")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "body[data-api3-ok]")) > 0
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "input[name=\"report_as_api\"][value=\"N\"]")).is_selected() else element.click()
    None if (element := self.driver.find_element(By.CSS_SELECTOR, "input[name=\"report_as_public\"][value=\"N\"]")).is_selected() else element.click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"][value=\"Save Report\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
