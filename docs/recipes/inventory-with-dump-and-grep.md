---
eyebrow: 'Docs · Recipes'
lede:    'Extract a tag list from a server: dump:nodeset to XML, then grep / xmlstarlet / Python to slice it. Useful for vendor onboarding and address-space audits.'

see_also:
  - { href: '../commands/dump-nodeset.md',     meta: '4 min' }
  - { href: '../commands/browse.md',           meta: '4 min' }
  - { href: '../code-generation/dump-from-server.md', meta: '5 min' }

prev: { label: 'Live monitoring', href: './live-monitoring-with-watch.md' }
next: { label: 'No further',      href: '#' }
---

# Inventory with dump and grep

For one-off audits — "what does this server actually expose?" —
`dump:nodeset` + standard text tools beat custom code every time.
The XML is grep-able, `xmlstarlet`-friendly, and parseable by
any language.

## The minimum

<!-- @code-block language="bash" label="bash — dump and inspect" -->
```bash
opcua-cli dump:nodeset opc.tcp://plc.local:4840 \
    --output=PLC.NodeSet2.xml \
    --namespace=2

# How many nodes?
grep -c '<UAVariable\|<UAObject\|<UAMethod' PLC.NodeSet2.xml

# How many Variable nodes?
grep -c '<UAVariable' PLC.NodeSet2.xml
```
<!-- @endcode-block -->

Five-second answers to "how big is this server" without running
multiple `browse`s or building a tree in code.

## Extracting a tag list

Variables are the things that hold values. Extract their names
and NodeIds:

<!-- @code-block language="bash" label="bash — variable inventory with xmlstarlet" -->
```bash
xmlstarlet sel -N u="http://opcfoundation.org/UA/2011/03/UANodeSet.xsd" \
    -t -m '//u:UAVariable' \
    -v '@NodeId' -o $'\t' -v '@BrowseName' -n \
    PLC.NodeSet2.xml
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="output" -->
```text
ns=2;i=1001    PLC/Speed
ns=2;i=1002    PLC/Mode
ns=2;i=1003    PLC/Health
ns=2;i=2001    Pump/FlowRate
ns=2;i=2002    Pump/Pressure
...
```
<!-- @endcode-block -->

Pipe to `sort`, `wc`, `column` to your taste. The output is
tab-separated, suitable for spreadsheet import.

## Filtering by DataType

A common audit question: "which tags hold `Double` values?"
Variable nodes have a `DataType` attribute referencing a
DataType NodeId. For `Double`, that's `i=11`:

<!-- @code-block language="bash" label="bash — filter by DataType" -->
```bash
xmlstarlet sel -N u="http://opcfoundation.org/UA/2011/03/UANodeSet.xsd" \
    -t -m '//u:UAVariable[@DataType="i=11"]' \
    -v '@NodeId' -o $'\t' -v '@BrowseName' -n \
    PLC.NodeSet2.xml
```
<!-- @endcode-block -->

The catalogue of well-known DataTypes (in namespace 0) is in
the OPC UA spec — `i=1` Boolean, `i=6` Int32, `i=11` Double,
`i=12` String, … See [`opcua-client` —
BuiltinType](https://github.com/php-opcua/opcua-client/blob/master/docs/types/overview.md).

## Counting per namespace

<!-- @code-block language="bash" label="bash — per-namespace counts" -->
```bash
xmlstarlet sel -N u="http://opcfoundation.org/UA/2011/03/UANodeSet.xsd" \
    -t -m '//u:UAVariable' -v '@NodeId' -n \
    PLC.NodeSet2.xml \
  | sed -E 's/^(ns=[0-9]+).*/\1/; t; s/^/ns=0/' \
  | sort | uniq -c
```
<!-- @endcode-block -->

<!-- @code-block language="text" label="output" -->
```text
  150 ns=0
 2854 ns=2
   42 ns=3
```
<!-- @endcode-block -->

Tells you which namespace dominates. If `ns=2` has 2 854 tags
and you only care about a subset, narrow your next dump.

## Building a "tag explorer" CSV

For non-technical operators:

<!-- @code-block language="bash" label="bash — CSV export" -->
```bash
xmlstarlet sel -N u="http://opcfoundation.org/UA/2011/03/UANodeSet.xsd" \
    -t -m '//u:UAVariable' \
    -v '@NodeId' -o ',' \
    -v '@BrowseName' -o ',' \
    -v '@DataType' -o ',' \
    -v 'u:DisplayName' -n \
    PLC.NodeSet2.xml \
    > tags.csv

# Add a header
sed -i '1i NodeId,BrowseName,DataType,DisplayName' tags.csv
```
<!-- @endcode-block -->

Import to Excel / Google Sheets, give the operator a searchable
inventory of the address space.

## Comparing two snapshots — what changed?

Periodic dumps catch firmware-update surprises:

<!-- @code-block language="bash" label="bash — diff snapshots" -->
```bash
# Yesterday's snapshot
opcua-cli dump:nodeset opc.tcp://plc.local:4840 \
    --output=PLC-$(date -u +%Y%m%d).NodeSet2.xml --namespace=2

# Today's
opcua-cli dump:nodeset opc.tcp://plc.local:4840 \
    --output=PLC-$(date -u +%Y%m%d).NodeSet2.xml --namespace=2

# Diff the BrowseName lists (NodeIds change less often)
diff <(xmlstarlet sel -t -m '//*[local-name()="UAVariable"]' -v '@BrowseName' -n PLC-20260514.NodeSet2.xml | sort -u) \
     <(xmlstarlet sel -t -m '//*[local-name()="UAVariable"]' -v '@BrowseName' -n PLC-20260515.NodeSet2.xml | sort -u)
```
<!-- @endcode-block -->

New tags, removed tags, renamed tags — visible in the diff.
Bake into CI for a "schema drift detector".

## Finding methods

Methods are nodes whose class is `UAMethod`. To list them:

<!-- @code-block language="bash" label="bash — method inventory" -->
```bash
xmlstarlet sel -N u="http://opcfoundation.org/UA/2011/03/UANodeSet.xsd" \
    -t -m '//u:UAMethod' \
    -v '@NodeId' -o $'\t' -v '@BrowseName' -n \
    PLC.NodeSet2.xml
```
<!-- @endcode-block -->

For each method NodeId, you can then `opcua-cli call <endpoint>
<methodId>` against the live server (or browse to find its
arguments).

## Extracting enum definitions

Enums are nodes whose class is `UADataType` with a definition
containing enumeration members:

<!-- @code-block language="bash" label="bash — enum extraction" -->
```bash
xmlstarlet sel -N u="http://opcfoundation.org/UA/2011/03/UANodeSet.xsd" \
    -t -m '//u:UADataType[u:Definition/@Name]' \
    -v '@NodeId' -o $'\t' -v '@BrowseName' -n \
    PLC.NodeSet2.xml
```
<!-- @endcode-block -->

For each enum, fetch the cases:

<!-- @code-block language="bash" label="bash — enum cases" -->
```bash
xmlstarlet sel -N u="http://opcfoundation.org/UA/2011/03/UANodeSet.xsd" \
    -t -m "//u:UADataType[@NodeId='ns=2;i=3001']/u:Definition/u:Field" \
    -v '@Name' -o '=' -v '@Value' -n \
    PLC.NodeSet2.xml
```
<!-- @endcode-block -->

For automatically-generated typed enums, the better path is
`generate:nodeset` — but `xmlstarlet` is faster when you need a
list once.

## When to switch to code

The CLI + xmlstarlet pattern is great for one-off audits. When
you need:

- **Repeatable, scheduled audits** — script it as a CI job that
  fails on schema drift.
- **Cross-server comparisons** — load multiple dumps, compare
  programmatically. Easier in Python or PHP than in shell.
- **Filtering by complex criteria** — anything involving multiple
  attributes or reference following exhausts shell quickly.

For all of those, generate typed PHP via `opcua-cli generate:nodeset`
and use the constants in a real application. The shell pattern
is for the "first look" phase.

## Performance

A 2 000-node namespace dumps in ~30 seconds (network-bound by
batched reads). A 50 000-node namespace can take minutes. Cache
the dump file — it doesn't change between firmware updates.

`xmlstarlet` is comfortable with files up to ~100 MB. For bigger
dumps, switch to a streaming parser (Python's `xml.etree`'s
`iterparse`, or PHP's `XMLReader`).
