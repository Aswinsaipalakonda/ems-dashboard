import sys
import json
import os

try:
    import fitz
except ImportError:
    print(json.dumps({"success": False, "error": "PyMuPDF (fitz) not installed"}))
    sys.exit(1)

def generate_pdf(data):
    template_path = os.path.abspath(r"assets/references/offer letter/template.pdf")
    if not os.path.exists(template_path):
        return {"success": False, "error": "Template PDF not found"}

    doc = fitz.open(template_path)
    page = doc[0]

    title = f"{data.get('employment_type', 'Internship').capitalize()} Offer Letter - {data.get('role_code', 'SDE')}"
    cand_name = data.get('candidate_name', 'Candidate')
    designation = data.get('designation', 'Software Development Engineer')
    duration = data.get('duration_text', '45 days')
    start_date = data.get('start_date', '')
    end_date = data.get('end_date', '')
    issue_date = data.get('issue_date', '')
    location = data.get('location', 'Hybrid')
    resp_list = data.get('responsibilities', [])
    terms_list = data.get('terms', [])
    emp_type = data.get('employment_type', 'internship')

    # 1. Insert Centered Title
    title_rect = fitz.Rect(40, 132, 555, 160)
    page.insert_textbox(title_rect, title, fontsize=17, fontname="helv", align=fitz.TEXT_ALIGN_CENTER)

    # 2. Build full text matching offer.pdf exactly
    resp_bullets = "\n".join([f"• {r}" for r in resp_list])
    terms_bullets = "\n".join([f"• {t}" for t in terms_list])

    body_text = f"""Date: {issue_date}
Subject: Offer Letter for {designation}

Dear {cand_name},

We are pleased to offer you the position of {designation} at Clientura, for a duration of {duration}, starting from {start_date} to {end_date}. This {emp_type} is part of our commitment to nurturing talented individuals and providing practical exposure to modern software engineering practices.

During your {emp_type}, you will work closely with our Engineering Team on real-world software development projects involving:
{resp_bullets}

You are expected to maintain a high level of professionalism, meet project deadlines, actively collaborate with team members, and contribute to the successful delivery of software solutions.

{emp_type.capitalize()} Details:
Position: {designation}
Location: {location}
Start Date: {start_date}
End Date: {end_date}

Terms & Conditions:
{terms_bullets}

To confirm your acceptance of this offer, please reply to this email. Upon receiving your confirmation, we will proceed with the onboarding process and provide further details regarding your {emp_type} responsibilities and project assignments.

We look forward to welcoming you to our team and supporting your professional growth as a {designation}."""

    content_rect = fitz.Rect(32, 172, 563, 725)
    page.insert_textbox(content_rect, body_text, fontsize=9.2, fontname="helv", lineheight=1.24)

    # 3. Sign-offs
    page.insert_text(fitz.Point(32, 735), "Warm regards,", fontsize=10, fontname="helv")
    page.insert_text(fitz.Point(32, 748), "Clientura", fontsize=10, fontname="helv")

    page.insert_text(fitz.Point(430, 775), "Signature of candidate", fontsize=10, fontname="helv")

    out_file = data.get('output_path')
    os.makedirs(os.path.dirname(os.path.abspath(out_file)), exist_ok=True)
    doc.save(out_file)
    doc.close()
    return {"success": True, "output_path": out_file}

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No input payload JSON provided"}))
        sys.exit(1)

    try:
        with open(sys.argv[1], 'r', encoding='utf-8') as f:
            payload = json.load(f)
        res = generate_pdf(payload)
        print(json.dumps(res))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}))
