import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const script = fs.readFileSync(
  fileURLToPath(new URL('../assets/utm-bridge.js', import.meta.url)),
  'utf8',
);

function run(pageUrl, hrefs) {
  const links = hrefs.map((href) => ({ href }));
  vm.runInNewContext(script, {
    URL,
    window: { location: { href: pageUrl } },
    document: { querySelectorAll: () => links },
  });
  return links.map((link) => link.href);
}

{
  const [result] = run(
    'https://blog.lazying.art/post?utm_source=reddit&utm_medium=profile&utm_campaign=local_knowledge_terminal_pilot',
    ['https://lazying.art/lkt/fit-check/?utm_source=lazyblog&utm_medium=article&utm_content=confidential_pdf_search'],
  );
  const target = new URL(result);
  assert.equal(target.searchParams.get('utm_source'), 'reddit');
  assert.equal(target.searchParams.get('utm_medium'), 'profile');
  assert.equal(target.searchParams.get('utm_campaign'), 'local_knowledge_terminal_pilot');
  assert.equal(target.searchParams.get('utm_content'), 'confidential_pdf_search');
}

{
  const [result] = run(
    'https://blog.lazying.art/post?utm_source=reddit&utm_medium=profile&utm_campaign=local_knowledge_terminal_pilot&utm_content=reddit_guide',
    ['https://lazying.art/lkt/sample-report/?utm_source=lazyblog&utm_medium=article&utm_content=confidential_pdf_sample_report'],
  );
  const target = new URL(result);
  assert.equal(target.pathname, '/lkt/sample-report/');
  assert.equal(target.searchParams.get('utm_source'), 'reddit');
  assert.equal(target.searchParams.get('utm_medium'), 'profile');
  assert.equal(target.searchParams.get('utm_campaign'), 'local_knowledge_terminal_pilot');
  assert.equal(target.searchParams.get('utm_content'), 'reddit_guide');
}

{
  const original = 'https://lazying.art/lkt/fit-check/?utm_source=lazyblog';
  assert.equal(run('https://blog.lazying.art/post', [original])[0], original);
}

{
  const links = run(
    'https://blog.lazying.art/post?utm_source=reddit%20bad&utm_medium=social',
    [
      'https://example.com/lkt/fit-check/?utm_source=external',
      'https://lazying.art/eink/?utm_source=original',
      'https://lazying.art/lkt/sample-report-extra/?utm_source=original',
      'https://lazying.art/lkt/fit-check/?utm_source=lazyblog',
    ],
  );
  assert.equal(links[0], 'https://example.com/lkt/fit-check/?utm_source=external');
  assert.equal(links[1], 'https://lazying.art/eink/?utm_source=original');
  assert.equal(links[2], 'https://lazying.art/lkt/sample-report-extra/?utm_source=original');
  const target = new URL(links[3]);
  assert.equal(target.searchParams.get('utm_source'), 'lazyblog');
  assert.equal(target.searchParams.get('utm_medium'), 'social');
}

console.log('utm-bridge tests passed');
