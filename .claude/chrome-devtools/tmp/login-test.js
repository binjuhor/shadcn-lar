import { getBrowser, getPage, disconnectBrowser, outputJSON, outputError } from '../../skills/chrome-devtools/scripts/lib/browser.js';

async function loginTest() {
  try {
    const browser = await getBrowser();
    const page = await getPage(browser);

    // Navigate to login
    await page.goto('https://shadcn-admin.test/login', {
      waitUntil: 'domcontentloaded',
      timeout: 60000
    });

    // Wait for email input and clear it
    const emailInput = await page.waitForSelector('#email');
    await emailInput.click({ clickCount: 3 }); // Triple click to select all
    await page.keyboard.press('Backspace');

    // Type email
    await page.type('#email', 'admin@example.com', { delay: 50 });

    // Wait for password input and clear it
    const passwordInput = await page.waitForSelector('#password');
    await passwordInput.click({ clickCount: 3 });
    await page.keyboard.press('Backspace');

    // Type password
    await page.type('#password', 'password', { delay: 50 });

    // Click login button
    await page.click('button.bg-primary');

    // Wait for navigation
    await page.waitForNavigation({
      waitUntil: 'domcontentloaded',
      timeout: 10000
    }).catch(() => {});

    await new Promise(r => setTimeout(r, 2000));

    outputJSON({
      success: true,
      url: page.url(),
      title: await page.title()
    });

    await disconnectBrowser();
  } catch (error) {
    outputError(error);
  }
}

loginTest();
