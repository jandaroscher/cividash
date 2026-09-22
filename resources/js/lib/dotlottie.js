// Registers the <dotlottie-wc> custom element and points its WASM renderer
// at our self-hosted copy instead of the jsdelivr/unpkg CDN default
// (no third-party hosts at runtime, see tests/Feature/Privacy/NoThirdPartyHostsTest.php).
import '@lottiefiles/dotlottie-wc';
import { setWasmUrl } from '@lottiefiles/dotlottie-wc';
import dotlottieWasmUrl from '@lottiefiles/dotlottie-web/dotlottie-player.wasm?url';

setWasmUrl(dotlottieWasmUrl);
