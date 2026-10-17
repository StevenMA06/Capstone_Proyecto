"""Leer las celdas del XLSX descargado, sin usar el exportador de la aplicación."""
import sys,json,zipfile,xml.etree.ElementTree as E
ns={'m':'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
with zipfile.ZipFile(sys.argv[1]) as z:
    assert z.testzip() is None
    names=[n for n in z.namelist() if n.startswith('xl/worksheets/sheet') and n.endswith('.xml')]
    result=[]
    shared=[]
    if 'xl/sharedStrings.xml' in z.namelist():
        shared=[''.join(x.itertext()) for x in E.fromstring(z.read('xl/sharedStrings.xml')).findall('m:si',ns)]
    for name in sorted(names):
        rows=[]
        for row in E.fromstring(z.read(name)).findall('.//m:sheetData/m:row',ns):
            values=[]
            for cell in row.findall('m:c',ns):
                if cell.get('t')=='inlineStr': value=''.join(t.text or '' for t in cell.findall('.//m:t',ns))
                else:
                    value=cell.findtext('m:v','',ns)
                    if cell.get('t')=='s':value=shared[int(value)]
                values.append(value)
            rows.append(values)
        result.append({'sheet':name,'rows':rows})
print(json.dumps(result,ensure_ascii=True))
