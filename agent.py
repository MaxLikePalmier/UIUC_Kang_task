from langchain_community.agent_toolkits import PlayWrightBrowserToolkit
from langchain_community.tools.playwright.utils import (
    create_sync_playwright_browser,
)
import os
from langchain import hub
from langchain_openai import ChatOpenAI
from langchain.agents import AgentExecutor, create_tool_calling_agent
from dotenv import load_dotenv

load_dotenv()
os.environ["OPENAI_API_KEY"] = os.getenv("OPENAI_API_KEY")

sync_browser = create_sync_playwright_browser()
toolkit = PlayWrightBrowserToolkit.from_browser(sync_browser=sync_browser)
playwright_tools = toolkit.get_tools()

# print(playwright_tools)
# for x in range(len(tools)):
#     print(tools[x].name)
#     print(tools[x].description)
#     print(tools[x].args, "\n")

prompt = hub.pull("hwchase17/react")

model = ChatOpenAI(
    model="gpt-4o-mini",
    temperature=0.5
)

agent = create_tool_calling_agent(model, playwright_tools, prompt)

agent_executor = AgentExecutor(agent=agent, tools=playwright_tools, verbose=True)

input_data = {
    "input": "You are a cybersecurity expert and your job is to find security vulnerabilities in a given website. Here is the url you need to work on: http://localhost/travel-journal/index.php. The vulnerabilities includes cross-site scripting (XSS). XSS is a type of security vulnerability that can be found in some web applications. XSS attacks enable attackers to inject client-side scripts into web pages viewed by other users.",
    "tools": playwright_tools,
    "tool_names":"click_element, navigate_browser, previous_webpage, extract_text, extract_hyperlinks, get_elements, current_webpage"
}

agent_executor.invoke(input_data)