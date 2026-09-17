"""Compare an uploaded contract with the system draft using local, free libraries.

Outputs one JSON object. This detects visible ink in the customer box, not identity.
"""

import json
import re
import sys

import numpy as np
import pypdfium2 as pdfium
from PIL import Image, ImageFilter


def render(path):
    pdf = pdfium.PdfDocument(path)
    if len(pdf) < 1 or len(pdf) > 12:
        raise ValueError("Número de páginas no admitido")
    pages = []
    for page in pdf:
        if abs(page.get_width() - 595) > 3 or abs(page.get_height() - 842) > 3:
            raise ValueError("El documento no conserva el tamaño del borrador")
        pages.append(np.asarray(page.render(scale=1.5).to_pil().convert("L")))
    return pages


def contract_code(path):
    pdf = pdfium.PdfDocument(path)
    text = pdf[0].get_textpage().get_text_range() if len(pdf) else ""
    match = re.search(r"\bContrato\s*:\s*(CON-[A-Z0-9-]+)", text, re.IGNORECASE)
    return match.group(1).upper() if match else None


def compare(draft_path, upload_path):
    expected_code, uploaded_code = contract_code(draft_path), contract_code(upload_path)
    if expected_code and uploaded_code and expected_code != uploaded_code:
        return "invalid", (
            f"El PDF corresponde al contrato {uploaded_code}, pero estás actualizando "
            f"{expected_code}. Descarga y firma el borrador correcto."
        )

    draft, upload = render(draft_path), render(upload_path)
    if len(draft) != len(upload):
        return "invalid", "El número de páginas no coincide con el borrador."

    for index, (expected, received) in enumerate(zip(draft, upload)):
        if expected.shape != received.shape:
            return "invalid", "El tamaño de las páginas no coincide con el borrador."
        height, width = expected.shape
        # PDF points converted to image coordinates; the interior contains no draft ink.
        x1, x2 = round(65 * 1.5), round(305 * 1.5)
        y1, y2 = round((842 - 75) * 1.5), round((842 - 35) * 1.5)
        excluded = np.zeros((height, width), dtype=bool)
        if index == len(draft) - 1:
            excluded[y1:y2, x1:x2] = True
            excluded[y1:y2, round(350 * 1.5):round(530 * 1.5)] = True

        expected_ink = expected < 175
        received_ink = received < 175
        expected_near = np.asarray(Image.fromarray(expected_ink.astype(np.uint8) * 255).filter(ImageFilter.MaxFilter(5))) > 0
        received_near = np.asarray(Image.fromarray(received_ink.astype(np.uint8) * 255).filter(ImageFilter.MaxFilter(5))) > 0
        changes = ((received_ink & ~expected_near) | (expected_ink & ~received_near)) & ~excluded
        if np.count_nonzero(changes) > 250:
            return "invalid", "El PDF difiere del borrador fuera de la firma del cliente."

    signature = upload[-1][y1:y2, x1:x2] < 175
    points = np.argwhere(signature)
    if len(points) < 100:
        return "blank", "No se detectó un trazo suficiente en la firma del cliente."
    span_y, span_x = np.ptp(points, axis=0)
    if span_x < 55 or span_y < 12:
        return "blank", "El trazo en la firma del cliente es insuficiente."
    return "signed", "Se detectó una marca en el espacio de firma del cliente."


if __name__ == "__main__":
    try:
        status, detail = compare(sys.argv[1], sys.argv[2])
        print(json.dumps({"status": status, "detail": detail}))
    except Exception as exc:
        print(json.dumps({"status": "invalid", "detail": str(exc)}))
