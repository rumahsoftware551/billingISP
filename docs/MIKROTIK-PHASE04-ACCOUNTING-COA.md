# MikroTik Setup — Jaringanku Phase 04

Use the actual LAN/VPN address of the Windows/VPS host running Jaringanku as `<JARINGANKU_RADIUS_IP>` and use the same strong secret configured in the Jaringanku NAS record.

## 1. RADIUS client for PPP

```routeros
/radius add service=ppp address=<JARINGANKU_RADIUS_IP> authentication-port=1812 accounting-port=1813 secret=<STRONG_SHARED_SECRET>
```

Verify:

```routeros
/radius print detail
/radius monitor 0
```

## 2. Enable PPP authentication and accounting

```routeros
/ppp aaa set use-radius=yes accounting=yes interim-update=0s
```

Jaringanku sends `Acct-Interim-Interval` in Access-Accept (default 300 seconds per Internet Plan). Keeping the RouterOS local `interim-update` at `0s` lets the RADIUS-provided interval control the session update cadence.

## 3. Enable unsolicited RADIUS messages (CoA / Disconnect)

RouterOS defaults `/radius incoming` to port 1700. Jaringanku uses port 3799 by default, so configure it explicitly:

```routeros
/radius incoming set accept=yes port=3799
/radius incoming print
```

The `coa_port` in Jaringanku NAS must match this value.

## 4. Firewall

Permit only the Jaringanku host to send UDP 3799 to the MikroTik. Do not expose the incoming RADIUS control port to the public Internet without network-layer restrictions/VPN.

Example concept (adapt interface/address-list to your network policy):

```routeros
/ip firewall filter add chain=input protocol=udp dst-port=3799 src-address=<JARINGANKU_RADIUS_IP> action=accept comment="Jaringanku CoA/Disconnect"
```

Place the rule appropriately before a general input drop rule.

## 5. Validate accounting

After a PPPoE client connects, open:

```text
http://localhost:8080/network/sessions
```

You should see:

- PPPoE username
- Acct-Session-Id
- framed IP
- Calling-Station-Id / MAC
- NAS-IP-Address
- start/update times
- accounting octets

## 6. Validate CoA

Use **CoA Paket** in Online Sessions. Expected reply:

```text
CoA-ACK
```

Phase 04 sends the current Internet Plan's `Mikrotik-Rate-Limit`.

## 7. Validate Disconnect

Use **Disconnect**. Expected reply:

```text
Disconnect-ACK
```

The session may remain in Jaringanku for a moment until RouterOS sends its final Accounting-Stop.

## Official references

- RouterOS RADIUS: https://help.mikrotik.com/docs/spaces/ROS/pages/328097/RADIUS
- RouterOS PPP AAA: https://help.mikrotik.com/docs/spaces/ROS/pages/132350049/PPP%2BAAA
- FreeRADIUS radclient: https://www.freeradius.org/radiusd/man/radclient.html
