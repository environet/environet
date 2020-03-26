export class Cookie {

	static get(name) {
		name = name + "=";
		let decodedCookie = decodeURIComponent(document.cookie);
		let ca = decodedCookie.split(';');
		for (let i = 0; i < ca.length; i++) {
			let c = ca[i];
			while (c.charAt(0) === ' ') {
				c = c.substring(1);
			}
			if (c.indexOf(name) === 0) {
				return c.substring(name.length, c.length);
			}
		}
		return null;
	}

	static set(name, value) {
		const d = new Date();
		d.setTime(d.getTime() + (24 * 60 * 60 * 1000));
		document.cookie = `${name}=${value};expires${d.toUTCString()};path=/`;
	}
}