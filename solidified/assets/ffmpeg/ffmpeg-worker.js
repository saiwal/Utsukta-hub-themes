'use strict';
// Classic Web Worker — loads @ffmpeg/core via importScripts, then runs exec()
// in this thread (not the main thread).
//
// Progress: setProgress only fires once at ratio=1 (end of encode), so instead
// we use setLogger to parse FFmpeg's stderr "time=HH:MM:SS.xx" output.  Each
// log line is a synchronous WASM→JS callback that calls self.postMessage(), so
// the main thread receives live progress while exec() is still running.

let core = null;
let _outputDuration = 0;

async function initCore(baseUrl) {
  if (core) return;
  importScripts(baseUrl + 'ffmpeg-core.js');
  const factory = self.createFFmpegCore;
  if (!factory) throw new Error('createFFmpegCore not found after importScripts');
  core = await factory({
    locateFile: (name) => baseUrl + name,
    print:    () => {},
    printErr: () => {},
  });
  core.setLogger(({ message }) => {
    // FFmpeg writes "frame=... time=HH:MM:SS.xx ..." to stderr each update
    const m = message.match(/time=(-?)(\d{2}):(\d{2}):(\d{2}[.,]\d+)/);
    if (m && _outputDuration > 0) {
      if (m[1] === '-') return; // negative time means before start, ignore
      const secs = parseInt(m[2]) * 3600 + parseInt(m[3]) * 60 + parseFloat(m[4].replace(',', '.'));
      self.postMessage({ type: 'progress', ratio: Math.min(0.99, secs / _outputDuration) });
    }
  });
}

self.onmessage = async function ({ data }) {
  const { id, type, payload } = data;
  try {
    switch (type) {
      case 'init': {
        await initCore(payload.baseUrl);
        self.postMessage({ id, type: 'ok' });
        break;
      }
      case 'write': {
        core.FS.writeFile(payload.name, new Uint8Array(payload.buffer));
        self.postMessage({ id, type: 'ok' });
        break;
      }
      case 'exec': {
        _outputDuration = payload.durationSec || 0;
        core.setTimeout(-1);
        core.exec(...payload.args);
        const ret = core.ret;
        core.reset();
        self.postMessage({ id, type: 'ok', ret });
        break;
      }
      case 'read': {
        const raw = core.FS.readFile(payload.name);
        const arr = new Uint8Array(raw);
        const copy = arr.buffer.slice(arr.byteOffset, arr.byteOffset + arr.byteLength);
        self.postMessage({ id, type: 'file', buffer: copy }, [copy]);
        break;
      }
      case 'unlink': {
        try { core.FS.unlink(payload.name); } catch (_) { /* ignore */ }
        self.postMessage({ id, type: 'ok' });
        break;
      }
      default:
        self.postMessage({ id, type: 'error', message: 'Unknown message type: ' + type });
    }
  } catch (err) {
    self.postMessage({ id, type: 'error', message: err instanceof Error ? err.message : String(err) });
  }
};
