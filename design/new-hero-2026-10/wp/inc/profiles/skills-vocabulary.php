<?php
/**
 * Profiles track: the shared skills vocabulary for autocomplete (card #6347: seed with 200
 * common skills). Members can still type any skill; this list only suggests.
 */

defined( 'ABSPATH' ) || exit;

/** @return string[] */
function prospergenics_profiles_skills_vocabulary() {
    $skills = array(
        // business and management
        'Accounting', 'Agile', 'Budgeting', 'Business Analysis', 'Business Development', 'Business Strategy',
        'Change Management', 'Coaching', 'Conflict Resolution', 'Contract Negotiation', 'Cost Control',
        'Customer Service', 'Customer Success', 'Decision Making', 'Entrepreneurship', 'Event Planning',
        'Financial Analysis', 'Financial Modeling', 'Fundraising', 'Grant Writing', 'Human Resources',
        'Inventory Management', 'Leadership', 'Lean Management', 'Logistics', 'Management',
        'Market Research', 'Mentoring', 'Negotiation', 'Operations Management', 'Organizational Development',
        'People Management', 'Performance Management', 'Planning', 'Problem Solving', 'Process Improvement',
        'Procurement', 'Product Management', 'Program Management', 'Project Management', 'Public Speaking',
        'Quality Assurance', 'Recruiting', 'Risk Management', 'Sales', 'Scrum', 'Stakeholder Management',
        'Strategic Planning', 'Supply Chain Management', 'Team Building', 'Time Management', 'Training',
        'Vendor Management', 'Bookkeeping', 'Payroll', 'Tax Preparation', 'Auditing', 'Microfinance',
        'Cooperative Management', 'Social Entrepreneurship', 'Impact Measurement',
        // marketing and communication
        'Brand Management', 'Content Marketing', 'Content Strategy', 'Copywriting', 'Communication',
        'Community Management', 'Digital Marketing', 'Email Marketing', 'Growth Marketing', 'Journalism',
        'Marketing Strategy', 'Media Relations', 'Online Advertising', 'Photography', 'Podcasting',
        'Public Relations', 'Search Engine Optimization (SEO)', 'Social Media Marketing', 'Storytelling',
        'Technical Writing', 'Translation', 'Video Editing', 'Videography', 'Writing', 'Editing',
        'Proofreading', 'Presentation Skills', 'Customer Relationship Management (CRM)',
        // design
        'Adobe Illustrator', 'Adobe Photoshop', 'Animation', 'Brand Identity', 'Canva', 'Figma',
        'Graphic Design', 'Illustration', 'Interaction Design', 'Motion Graphics', 'Product Design',
        'Typography', 'User Experience (UX)', 'User Interface Design', 'User Research', 'Web Design',
        '3D Modeling', 'Fashion Design', 'Interior Design',
        // software and data
        'Android Development', 'Angular', 'API Design', 'AWS', 'Azure', 'C#', 'C++', 'Cloud Computing',
        'CSS', 'Cybersecurity', 'Data Analysis', 'Data Engineering', 'Data Science', 'Data Visualization',
        'Database Design', 'DevOps', 'Docker', 'Excel', 'Git', 'Go', 'Google Cloud', 'HTML', 'iOS Development',
        'Java', 'JavaScript', 'Kotlin', 'Kubernetes', 'Linux', 'Machine Learning', 'Artificial Intelligence',
        'Prompt Engineering', 'Mobile App Development', 'MySQL', 'Network Administration', 'Node.js',
        'PHP', 'Power BI', 'PostgreSQL', 'Python', 'R', 'React', 'Ruby', 'Rust', 'Software Architecture',
        'Software Development', 'Software Testing', 'SQL', 'Statistics', 'Swift', 'System Administration',
        'Tableau', 'TypeScript', 'Vue.js', 'Web Development', 'WordPress', 'IT Support', 'Laravel',
        '.NET', 'Flutter', 'Natural Language Processing', 'Computer Vision', 'Blockchain', 'No-Code Tools',
        'Automation', 'Spreadsheets', 'Google Workspace', 'Microsoft Office',
        // engineering, trades, agriculture, health
        'Agriculture', 'Agribusiness', 'Agronomy', 'Animal Husbandry', 'Aquaculture', 'Beekeeping',
        'Carpentry', 'Civil Engineering', 'Construction Management', 'Electrical Engineering',
        'Electrical Installation', 'Environmental Science', 'Food Processing', 'Horticulture',
        'Irrigation', 'Mechanical Engineering', 'Plumbing', 'Renewable Energy', 'Solar Installation',
        'Sustainability', 'Tailoring', 'Welding', 'Water Management', 'Permaculture', 'Poultry Farming',
        'Dairy Farming', 'Health Care', 'Nursing', 'Nutrition', 'First Aid', 'Public Health', 'Pharmacy',
        'Counseling', 'Psychology', 'Social Work', 'Teaching', 'Curriculum Development', 'E-Learning',
        'Instructional Design', 'Early Childhood Education', 'Research', 'Policy Analysis', 'Law',
        'Compliance', 'Real Estate', 'Hospitality', 'Tourism', 'Cooking', 'Driving', 'Music Production',
        'Swahili', 'English', 'Dutch', 'French', 'Arabic',
    );
    $skills = array_values( array_unique( $skills ) );
    sort( $skills, SORT_NATURAL | SORT_FLAG_CASE );
    return $skills;
}
