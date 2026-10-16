"""Documentos ficticios: trazo sintético visible, nunca firma de una persona real."""
import io
import sys
from pathlib import Path

from pypdf import PdfReader, PdfWriter
from reportlab.pdfgen import canvas

if sys.argv[1] == '--text':
    print('\n'.join(page.extract_text() or '' for page in PdfReader(sys.argv[2]).pages))
    sys.exit(0)

source, target = map(Path, sys.argv[1:3])
reader = PdfReader(source)
overlay = io.BytesIO()
page = reader.pages[-1]
drawing = canvas.Canvas(overlay, pagesize=(float(page.mediabox.width), float(page.mediabox.height)))
drawing.setStrokeColorRGB(0, 0, 0)
drawing.setLineWidth(1.6)
stroke = drawing.beginPath()
stroke.moveTo(90, 45)
for x, y in [(105, 62), (116, 43), (130, 60), (145, 46), (164, 65), (182, 44), (204, 57)]:
    stroke.lineTo(x, y)
drawing.drawPath(stroke)
drawing.save()
overlay.seek(0)
page.merge_page(PdfReader(overlay).pages[0])
writer = PdfWriter()
for page in reader.pages:
    writer.add_page(page)
with target.open('wb') as output:
    writer.write(output)
