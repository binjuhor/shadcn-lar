#!/usr/bin/env node
/**
 * Navigate to a URL
 * Usage: node navigate.js --url https://example.com [--wait-until networkidle2] [--timeout 30000]
 *
 * Session behavior:
 *   --close false  : Keep browser running, disconnect from it (default for chaining)
 *   --close true   : Close browser completely and clear session
 */
import { getBrowser, getPage, closeBrowser, disconnectBrowser, parseArgs, outputJSON, outputError } from './lib/browser.js';

async function navigate() {
  const args = parseArgs(process.argv.slice(2));

  if (!args.url) {
    outputError(new Error('--url is required'));
    return;
  }

  try {
    const browser = await getBrowser({
      headless: args.headless !== 'false'
    });

    const page = await getPage(browser);

    const options = {
      waitUntil: args['wait-until'] || 'networkidle2',
      timeout: parseInt(args.timeout || '30000')
    };

    await page.goto(args.url, options);

    const result = {
      success: true,
      url: page.url(),
      title: await page.title()
    };

    outputJSON(result);

    // Default: disconnect to keep browser running for session persistence
    // Use --close true to fully close browser
    if (args.close === 'true') {
      await closeBrowser();
    } else {
      await disconnectBrowser();
    }
  } catch (error) {
    outputError(error);
  }
}

navigate();
