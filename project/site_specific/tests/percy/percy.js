const puppeteer = require('puppeteer');
const percySnapshot = require('@percy/puppeteer');
const scrollToBottom = require('scroll-to-bottomjs');
const { execSync } = require('child_process');

let branch = execSync('git rev-parse --abbrev-ref HEAD', { encoding: 'utf8' }).trim();
let siteFull = `https://${branch}.colby-museum-frontend.pages.dev`;

(async () => {
    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    const scrollOptions = {
        frequency: 100,
        timing: 200, // milliseconds
    };

    // Test Page
    const testPage = await browser.newPage();
    await testPage.goto(`${siteFull}/demo-page`);
    await new Promise(function (resolve) {
        setTimeout(async function () {
            await testPage.evaluate(scrollToBottom, scrollOptions);
            await percySnapshot(testPage, 'Snapshot of test page');
            resolve();
        }, 3000);
    });

    await browser.close();
})();
