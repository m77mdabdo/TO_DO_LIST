# TO_DO_LIST
A simple and efficient To-Do List application built with PHP and MySQL . 

# ✅ To-Do List (PHP & MySQL)

A simple and efficient **To-Do List** web application built using **PHP**, **MySQL**, and **Bootstrap**.  
This app allows users to **add, edit, delete, and manage** their tasks with ease.

---

## 📌 Features
- 📝 **Add Tasks** - Easily add new tasks to your list.  
- ✏ **Edit Tasks** - Modify existing tasks.  
- ❌ **Delete Tasks** - Remove completed or unnecessary tasks.  
- 📌 **Task Status Management** - Categorize tasks into:
  - **All** 📌
  - **Doing** ⏳
  - **Done** ✅  
- 🔒 **Session Handling** - Display error messages when needed.  
- 🛠 **Custom Validation** - Ensures proper input using PHP validation classes.

- 🏗 Technologies Used
- php
- mysql
- oop
- solid
- html
- css
- Bootstrap

---

## 📂 Folder Structure
O_DO_LIST/ │── assets/css/ # Stylesheets (Bootstrap & custom styles) 
│── classes/ # Core PHP classes │ ├── validation/ # Validation classes │ │ ├── preg_match.php │ │ ├── required.php │ │ ├── str.php │ │ ├── strlen.php │ │ ├── validation.php │ │ ├── validator.php │ ├── Request.php │ ├── sessionClass.php ...
│── handle/ # Handles CRUD operations │ ├── addToDo.php │ ├── delete.php │ ├── edit.php │ ├── goto.php....
│── inc/ # Includes (DB connection, header, etc.) │ ├── connection.php │ ├── header.php │ .....
├── 404.php │── App.php # Main application logic │── edit.php # Edit To-Do page │── index.php # Main page │.....
