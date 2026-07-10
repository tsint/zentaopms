import {existsSync} from 'fs';
import {execFileSync} from 'child_process';
import {pathToFileURL} from 'url';

async function importPlaywrightFrom(specifier) {
  const mod = await import(specifier);
  return mod.default || mod;
}

function moduleSpecifier(value) {
  if(!value) return '';
  if(value.startsWith('file://')) return value;
  if(value.startsWith('/') || value.startsWith('./') || value.startsWith('../')) return pathToFileURL(value).href;
  return value;
}

function addPackageCandidates(candidates, root) {
  if(!root) return;
  candidates.push(
    pathToFileURL(`${root}/playwright/index.js`).href,
    pathToFileURL(`${root}/@playwright/test/index.js`).href
  );
}

function commandOutput(command, args) {
  try {
    return execFileSync(command, args, {encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore']}).trim();
  } catch(e) {
    return '';
  }
}

async function globalPlaywrightCandidates() {
  const candidates = [];
  if(process.env.PLAYWRIGHT_MODULE) candidates.push(moduleSpecifier(process.env.PLAYWRIGHT_MODULE));

  addPackageCandidates(candidates, commandOutput('npm', ['root', '-g']));

  const bunGlobalBin = commandOutput('bun', ['pm', '-g', 'bin']);
  if(bunGlobalBin) {
    addPackageCandidates(candidates, `${bunGlobalBin.replace(/\/bin$/, '')}/install/global/node_modules`);
  }

  const playwrightBin = commandOutput('sh', ['-lc', 'command -v playwright']);
  if(playwrightBin) {
    const realBin = commandOutput('readlink', ['-f', playwrightBin]) || playwrightBin;
    const nodeModulesIndex = realBin.lastIndexOf('/node_modules/');
    if(nodeModulesIndex !== -1) {
      const globalRoot = realBin.slice(0, nodeModulesIndex + '/node_modules'.length);
      addPackageCandidates(candidates, globalRoot);
    }
  }

  return candidates.filter((candidate, index, all) => {
    if(all.indexOf(candidate) !== index) return false;
    if(!candidate.startsWith('file://')) return true;
    return existsSync(new URL(candidate));
  });
}

export async function loadPlaywright() {
  try {
    return await importPlaywrightFrom('playwright');
  } catch(e) {
    for(const candidate of await globalPlaywrightCandidates()) {
      try {
        return await importPlaywrightFrom(candidate);
      } catch(inner) {
        /* Try the next configured/global location. */
      }
    }
    throw e;
  }
}
