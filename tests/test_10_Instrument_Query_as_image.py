# Generated from Selenium IDE
# Test name: t10 Instrument Query as image
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_10_Instrument_Query_as_image:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_10_Instrument_Query_as_image(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[@id=\"table-proj_table\"][contains(.,'Advanced Reports Test')]")) > 0
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports Test").click()
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports").click()
    self.driver.find_element(By.LINK_TEXT, "Instrument Query").click()
    self.driver.execute_script("$.get( window.location.href.replace('page=view','page=instrument_view') + '&as_image=1', function(data,status,xhr){if(data.indexOf('PNG')>-1&&xhr.getResponseHeader('Content-Type')=='image/png'){document.body.setAttribute('data-img-ok','1')};document.body.setAttribute('data-img','1')} )")
    WebDriverWait(self.driver, 60).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "body[data-img]")))
    self.vars["imgDownloadOK"] = self.driver.execute_script("return $('body[data-img-ok]').length")
    assert(self.vars["imgDownloadOK"] == "1")
