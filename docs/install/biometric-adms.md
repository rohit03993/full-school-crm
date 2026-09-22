# Biometric ADMS (ZKTeco Pro + eSSL) — never-seen playbook

Use this when **Setup → Biometric devices** shows **Never seen**, a red “no device seen recently” bar, or punches never arrive.

CRM is the **cloud ADMS server**. Path: `/iclock` (`cdata` **and** eSSL `cdata.aspx`, `getrequest`).  
Same protocol family for **ZKTeco Pro / K40** and **eSSL** (ZKTeco board inside, e.g. X2008 / ZMM200_TFT / Push 2.0.x).

### Horizon X2008 (22 Sep 2026) — real block

tcpdump: school IP called every ~15s:

`GET /iclock/cdata.aspx?SN=BJ2C230860354&options=all&language=69&pushver=2.4.1&DeviceType=att`

K40 uses `/iclock/cdata` (no `.aspx`). CRM had only that. Laravel returned **404 HTML**. Machine kept **database X**. Last punch stayed empty. Green **Online** from PC curl was **not** the box.

Fix: nginx rewrite `.aspx` → same name without `.aspx`, and CRM routes for `cdata.aspx` in `routes/biometric.php`. After Save, next poll (~15s) got **GET OPTION**; X cleared.

Do **not** put this tutorial on CRM screens. Teach in Cursor; operators use Setup + this file.

Worked example (Horizon, 22 Sep 2026): SN **`BJ2C230860354`**, host **`horizon.taskbook.co.in`**, CloudPanel user **`taskbook-horizon`**. Do not copy that SN onto Motion or demo.

---

## 1. CRM row first

**Setup → Biometric devices → New / Edit**

| Field | Set |
|---|---|
| Serial (SN) | Sticker / System Info, **letter for letter** (no space) |
| Active | **ON** |
| Face gate | **OFF** for card + fingerprint |

CRM does **not** only accept K40-style serials. Any text up to 64 characters is valid (`BJ2C…`, K40 SN, etc.).

PIN on the machine = student **roll** / staff **Staff ID**, unique. Do not point the same machine at EasyWDMS / EasyTimePro **and** this CRM at the same time.

---

## 2. Machine Cloud Server (ADMS)

Firmware adds `/iclock`. You type **host only**.

| Field | Set |
|---|---|
| Server Mode | **ADMS** |
| Enable Domain Name | **ON** if you use a name |
| Server Address | `your-school-domain.in` only |
| Enable Proxy | **OFF** |

**Do not type** `https://`, `http://`, or `/iclock`.

| Enable Domain Name | Server Address |
|---|---|
| **ON** | Website name (nginx needs this Host) |
| **OFF** | Public **VPS IP** only — often **fails** on CloudPanel (wrong site) |

**Server Port**

- If the menu **has** a port (typical K40 Pro): try **80** first. CRM ADMS is HTTP on 80 unless you changed nginx. **4370** is SDK / USB, not cloud.
- If the menu **has no port** (typical eSSL X2008 6.0.4.8): firmware uses **HTTP 80**.

Newer K40 boards can often follow HTTPS. Old eSSL often **cannot**.

---

## 3. Ethernet (the cable, not ADMS)

| Field | Typical school LAN |
|---|---|
| Gateway | Router, e.g. **`192.168.1.1`** — **not** `0.0.0.0` |
| DNS | **`8.8.8.8`** — **not** `0.0.0.0` |
| DHCP | OFF is OK with a free static IP |
| TCP COMM.Port **4370** | Leave it. CRM does **not** use 4370 |

Save and **reboot** the machine after cloud + Ethernet changes.

---

## 4. How to read CRM vs Chrome vs curl

**Last seen** updates only when `/iclock/cdata` (or `getrequest`) runs with an **Active** SN that **exactly** matches the row.

| You see | Meaning |
|---|---|
| Only **`OK`** | Empty SN, **unknown SN**, or inactive device. Last seen **does not** change. |
| **`GET OPTION FROM: {SN}`** + `TimeZone=330` | CRM accepted the device. Handshake is good. |
| Chrome **https://…** works, machine never seen | Laptop used HTTPS. Box used **HTTP 80**. |
| **`301 Moved Permanently`** on `http://` | CloudPanel is forcing HTTPS. Old eSSL dies here. |

Code: `app/Http/Controllers/Biometric/AdmsIclockController.php` — unknown SN still returns `OK` on purpose (machines expect that word).

---

## 5. Tests (your PC, then the box)

Replace `HOST` and `SN`. Use **`http://`** to test the machine path.

```powershell
curl.exe -sS -D - -o - --max-redirs 0 "http://HOST/iclock/cdata?SN=EXACT-SN&options=all"
```

| Result | Next |
|---|---|
| **301** | Fix nginx (section 6). Do not change SN. |
| **200** + only `OK` | SN in the URL ≠ CRM row. Open **Edit**, copy SN, curl again. |
| **200** + `GET OPTION FROM:` | Server is ready. Refresh Biometric devices (Last seen may move from **this PC**). Then **reboot the machine**, wait 2 minutes. Last seen must move **again**. |

Then punch once. **Last punch** / **Today** and **Attendance** should move. If heartbeat is green but no punch, the **PIN/roll** does not match a student — not nginx.

---

## 6. CloudPanel: keep `/iclock` on HTTP (eSSL / no-TLS boards)

Staff login stays **`https://HOST`**. Only `/iclock` stays on port **80**.

**Do not** turn off Force HTTPS for the whole site.  
**Do not** edit Motion / demo vhosts when fixing Horizon (or the other way around). Site user folders stay separate.

### 6.1 Backup

CloudPanel → **Sites** → the school domain → **Vhost** → select all → Notepad copy.

Laravel + **Varnish** sites have **two** `server { }` blocks. Change only the **first** (listen 80 / 443). Leave **listen 8080** (PHP) as it is.

### 6.2 Replace the HTTPS-only `if`

Find:

```nginx
if ($scheme != "https") {
  rewrite ^ https://$host$request_uri permanent;
}
```

Replace with:

```nginx
set $redirect_https 1;
if ($request_uri ~* "^/iclock") {
  set $redirect_https 0;
}
if ($scheme = "https") {
  set $redirect_https 0;
}
if ($redirect_https = 1) {
  rewrite ^ https://$host$request_uri permanent;
}
```

### 6.3 Bypass Varnish for `/iclock`

Add **above** `location / {` (the `{{varnish_proxy_pass}}` block):

```nginx
location ^~ /iclock {
  proxy_pass http://127.0.0.1:8080;
  proxy_set_header Host $host;
  proxy_set_header X-Forwarded-Host $host;
  proxy_set_header X-Real-IP $remote_addr;
  proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
  proxy_redirect off;
  proxy_connect_timeout      60;
  proxy_send_timeout         60;
  proxy_read_timeout         60;
}
```

No trailing slash on `proxy_pass`. Punches must not be cached.

**Save** in CloudPanel. If nginx errors, paste the Notepad copy back.

If the site has **no** Varnish, skip 6.3. Only the `if` change is required so HTTP `/iclock` is not 301’d; PHP already lives in that same server block.

### 6.4 Security

| Still HTTPS | HTTP only |
|---|---|
| Login, fees, students, WhatsApp | `/iclock/*` (already public ADMS) |

Unknown SN is ignored. Active allowlist still applies. Punch packets (roll + time) can be seen on the school LAN — same as old EasyWDMS HTTP 80.

---

## 7. `.env` (server)

```env
BIOMETRIC_ADMS_ENABLED=true
BIOMETRIC_ADMS_REQUIRE_ALLOWLIST=true
BIOMETRIC_ADMS_PROCESS_INLINE=true
# BIOMETRIC_ADMS_TZ_OFFSET_MINUTES=330
```

If the list banner says **disabled in config**, ADMS is off — handshake always `OK` and Last seen never fills.

---

## 8. Quick split: K40 Pro vs eSSL X2008

| | ZKTeco Pro / K40 | eSSL X2008 (Horizon case) |
|---|---|---|
| Protocol | `/iclock` ADMS | Same |
| Domain ON + host | Yes | Yes |
| Server Port field | Often **yes** → 80 | Often **yes** — factory **8081** is wrong; use **80** or **8088** (host-inject) |
| HTTPS | Newer firmware may work | 6.0.4.8 often **cannot** |
| Typical fail | DNS/gateway `0.0.0.0`, port 4370, `https://` in address | Same, plus CloudPanel **301**, plus **`/iclock/cdata.aspx` 404** (Push 2.4.1) |

---

## 9. Code map (agents)

| Piece | File |
|---|---|
| Routes | `routes/biometric.php` (`cdata` and eSSL `cdata.aspx`) |
| HTTP | `app/Http/Controllers/Biometric/AdmsIclockController.php` |
| Handshake / punches | `app/Services/Biometric/BiometricAdmsIngestService.php` |
| Device row | `app/Models/BiometricDevice.php` (`touchSeen`) |
| Config | `config/biometric.php` |
| Tests | `tests/Feature/BiometricAdmsIngestTest.php` |
| Host-inject (old eSSL) | `scripts/adms-http-host-inject.py` — listen **8088**, add Host, forward to PHP **8080** |

---

## 10. Old eSSL: nginx 400 (no Host header)

Phone/curl work; machine keeps a database X; SN and IP/port 80 are already correct.

Cause: firmware sends HTTP/1.1 **without** `Host`. nginx returns **400** before `/iclock`.

Fix (Horizon example): run `scripts/adms-http-host-inject.py` on the VPS (port **8088**). Open **8088** in CloudPanel + host firewall. Machine: Domain **OFF**, server **VPS IP**, port **8088**. Do not bind this on Motion/demo.
